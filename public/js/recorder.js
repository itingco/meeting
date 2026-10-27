/* INGCO Meeting AI: browser-side crash/reconnect recovery via IndexedDB.
   Mobile browsers can still suspend microphone capture when the OS locks the screen.
   This file prevents automatic screen sleep where supported and resumes capture after interruptions. */
(() => {
  'use strict';
  const root = document.getElementById('recorder-root');
  if (!root) return;
  const $ = id => document.getElementById(id);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const base = root.dataset.urlBase;
  const micButton = $('mic-circle');
  const recoveryNotice = $('recovery-notice'), recoverButton = $('recover-button');

  let active = null, recorder = null, stream = null, wakeLock = null;
  let sequence = 0, uploadBusy = false, finalizeBusy = false, ticking = null;
  let persistTasks = [], manualStopRequested = false;
  let resumeBusy = false, unexpectedStopBusy = false, wakeLockWarningShown = false;

  const audioConstraints = {
    video: false,
    audio: {
      echoCancellation: true,
      noiseSuppression: true,
      channelCount: 1,
    },
  };

  // Live microphone meter uses the SAME MediaStream as MediaRecorder; no second permission.
  // It is input level only: it does not send audio and is not a speech recognizer.
  const meterCanvas = $('mic-waveform');
  const meterPanel = meterCanvas?.closest('.audio-meter');
  const meterSignal = $('mic-signal-label');
  const meterLevel = $('mic-level-label');
  let meterContext = null, meterSource = null, meterAnalyser = null;
  let meterFrame = 0, meterArray = null, meterRms = null, meterSmoothed = [];
  let lastVoice = false;

  const isRecording = () => !!recorder && recorder.state === 'recording';

  const setMeterLabel = (label, value, speaking = false, running = false) => {
    if (meterSignal) meterSignal.textContent = label;
    if (meterLevel) meterLevel.textContent = value;
    if (meterPanel) {
      meterPanel.classList.toggle('is-live', running);
      meterPanel.classList.toggle('is-speaking', speaking && running);
    }
  };

  const drawMeter = (levels = [], speaking = false) => {
    if (!meterCanvas) return;
    const ctx = meterCanvas.getContext('2d');
    if (!ctx) return;
    const rect = meterCanvas.getBoundingClientRect();
    const width = Math.max(140, rect.width || 400);
    const height = Math.max(50, rect.height || 84);
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = Math.round(width * dpr), h = Math.round(height * dpr);
    if (meterCanvas.width !== w || meterCanvas.height !== h) {
      meterCanvas.width = w; meterCanvas.height = h;
    }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);
    const mid = height / 2;
    ctx.beginPath(); ctx.moveTo(0, mid); ctx.lineTo(width, mid);
    ctx.strokeStyle = '#dce6ee'; ctx.lineWidth = 1; ctx.stroke();
    const bars = 48, gutter = 3;
    const bw = Math.max(2, (width - (bars - 1) * gutter) / bars);
    const step = (width - bw) / (bars - 1);
    ctx.fillStyle = speaking ? '#26a47b' : '#efa500';
    for (let i = 0; i < bars; i++) {
      const amp = Math.min(1, Math.max(0, levels[i] || 0));
      const bh = 3 + amp * Math.max(8, height - 10);
      const x = i * step;
      if (typeof ctx.roundRect === 'function') {
        ctx.beginPath(); ctx.roundRect(x, mid - bh / 2, bw, bh, bw / 2); ctx.fill();
      } else {
        ctx.fillRect(x, mid - bh / 2, bw, bh);
      }
    }
  };

  const stopMeter = async () => {
    if (meterFrame) cancelAnimationFrame(meterFrame);
    meterFrame = 0;
    try { meterSource?.disconnect(); } catch (_) {}
    try { meterAnalyser?.disconnect(); } catch (_) {}
    meterSource = null; meterAnalyser = null; meterArray = null; meterRms = null;
    const ctx = meterContext; meterContext = null;
    if (ctx) { try { await ctx.close(); } catch (_) {} }
    meterSmoothed = []; lastVoice = false;
    drawMeter(); setMeterLabel('Mikrofon tidak aktif', '--');
  };

  const startMeter = async audioStream => {
    await stopMeter();
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx || !meterCanvas?.getContext) {
      setMeterLabel('Grafik tidak didukung browser', '--'); return;
    }
    try {
      meterContext = new AudioCtx();
      meterSource = meterContext.createMediaStreamSource(audioStream);
      meterAnalyser = meterContext.createAnalyser();
      meterAnalyser.fftSize = 1024;
      meterAnalyser.smoothingTimeConstant = 0.67;
      meterSource.connect(meterAnalyser);
      meterArray = new Uint8Array(meterAnalyser.frequencyBinCount);
      meterRms = new Uint8Array(meterAnalyser.fftSize);
      if (meterContext.state === 'suspended') await meterContext.resume();
      let previousLabel = 0;
      const frame = t => {
        if (!meterAnalyser || !meterContext) return;
        meterAnalyser.getByteFrequencyData(meterArray);
        meterAnalyser.getByteTimeDomainData(meterRms);
        let sum = 0;
        for (let i = 0; i < meterRms.length; i++) {
          const v = (meterRms[i] - 128) / 128;
          sum += v * v;
        }
        const rms = Math.sqrt(sum / meterRms.length);
        const speaking = rms >= 0.018;
        const sensitivity = Math.min(1, rms * 10);
        const bars = 48;
        for (let i = 0; i < bars; i++) {
          const index = Math.min(meterArray.length - 1, 2 + Math.floor(Math.pow(i / bars, 1.65) * 180));
          const spectrum = meterArray[index] / 255;
          const amount = speaking ? Math.min(1, spectrum * 1.6 * (0.3 + sensitivity * 1.3)) : 0;
          const prior = meterSmoothed[i] || 0;
          meterSmoothed[i] = prior * 0.65 + amount * 0.35;
        }
        drawMeter(meterSmoothed, speaking);
        if (speaking !== lastVoice || t - previousLabel > 450) {
          setMeterLabel(
            speaking ? 'Ada suara masuk' : 'Suara rendah / hening',
            speaking ? 'INPUT AKTIF' : 'INPUT RENDAH',
            speaking,
            true
          );
          previousLabel = t; lastVoice = speaking;
        }
        meterFrame = requestAnimationFrame(frame);
      };
      setMeterLabel('Memantau input suara…', 'INPUT AKTIF', false, true);
      meterFrame = requestAnimationFrame(frame);
    } catch (err) {
      await stopMeter();
      setMeterLabel('Grafik tidak tersedia; rekaman tetap berjalan', '--');
    }
  };

  const dateStart = () => active?.started ? Date.now() - active.started : 0;

  const openDb = () => new Promise((resolve, reject) => {
    if (!window.indexedDB) {
      reject(new Error('Browser ini tidak mendukung IndexedDB untuk cadangan lokal.'));
      return;
    }
    const request = indexedDB.open('ingco-meeting-backup-v1', 1);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains('parts')) {
        const store = db.createObjectStore('parts', { keyPath: 'key' });
        store.createIndex('meetingId', 'meetingId', { unique: false });
      }
      if (!db.objectStoreNames.contains('state')) db.createObjectStore('state', { keyPath: 'key' });
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });

  const transact = async (store, mode, fn) => {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction(store, mode);
      let value;
      try { value = fn(transaction.objectStore(store)); }
      catch (err) { db.close(); reject(err); return; }
      transaction.oncomplete = () => { db.close(); resolve(value); };
      transaction.onerror = () => { db.close(); reject(transaction.error); };
      transaction.onabort = () => { db.close(); reject(transaction.error); };
    });
  };

  const requestStore = async (store, operation) => {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(store, 'readonly');
      const r = operation(tx.objectStore(store));
      r.onsuccess = () => resolve(r.result);
      r.onerror = () => reject(r.error);
      tx.oncomplete = () => db.close();
      tx.onerror = () => db.close();
    });
  };

  const getState = () => requestStore('state', s => s.get('active'));
  const saveState = state => transact('state', 'readwrite', s => s.put({ ...state, key: 'active' }));
  const clearState = () => transact('state', 'readwrite', s => s.delete('active'));
  const savePart = (uuid, n, blob, mime) => transact('parts', 'readwrite', s => s.put({
    key: uuid + ':' + n, meetingId: uuid, sequence: n, blob, mime
  }));
  const removePart = key => transact('parts', 'readwrite', s => s.delete(key));
  const allParts = async uuid => {
    const parts = await requestStore('parts', s => s.index('meetingId').getAll(uuid));
    return parts.sort((a, b) => a.sequence - b.sequence);
  };

  const setError = message => {
    $('recorder-error').textContent = message;
    $('recorder-error').classList.remove('hidden');
  };
  const clearError = () => $('recorder-error').classList.add('hidden');
  const notify = message => {
    $('recorder-success').textContent = message;
    $('recorder-success').classList.remove('hidden');
  };
  const hideNotification = () => $('recorder-success').classList.add('hidden');

  const formatTime = seconds => {
    const s = Math.max(0, Math.floor(seconds));
    return [Math.floor(s / 3600), Math.floor(s / 60) % 60, s % 60]
      .map(x => String(x).padStart(2, '0')).join(':');
  };

  const jsonCall = async (url, options = {}) => {
    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', ...options.headers };
    const res = await fetch(url, { credentials: 'same-origin', ...options, headers });
    const text = await res.text(); let obj;
    try { obj = JSON.parse(text); }
    catch (e) {
      throw new Error('Respons server bukan JSON. Cek login, APP_DEBUG dan laravel.log (HTTP ' + res.status + ').');
    }
    if (!res.ok) {
      const message = obj.error || obj.message || (obj.errors ? Object.values(obj.errors).flat().join('; ') : null);
      throw new Error(message || 'Server gagal dengan HTTP ' + res.status);
    }
    return obj;
  };

  const requestWakeLock = async (showWarning = false) => {
    if (!active || active.phase !== 'recording' || document.visibilityState !== 'visible') return false;

    if (!navigator.wakeLock?.request) {
      if (showWarning && !wakeLockWarningShown) {
        wakeLockWarningShown = true;
        notify('Browser ini tidak mendukung Screen Wake Lock. Matikan auto-lock layar selama meeting agar rekaman tidak terputus.');
      }
      return false;
    }

    try {
      if (wakeLock && !wakeLock.released) return true;

      const sentinel = await navigator.wakeLock.request('screen');
      wakeLock = sentinel;
      sentinel.addEventListener('release', () => {
        if (wakeLock === sentinel) wakeLock = null;
        if (active?.phase === 'recording' && document.visibilityState === 'visible' && !manualStopRequested) {
          setTimeout(() => requestWakeLock(false), 250);
        }
      }, { once: true });
      return true;
    } catch (err) {
      wakeLock = null;
      if (showWarning && !wakeLockWarningShown) {
        wakeLockWarningShown = true;
        notify('Tidak bisa menahan layar tetap menyala di browser ini. Rekaman tetap berjalan selama browser/OS tidak menonaktifkan mikrofon.');
      }
      return false;
    }
  };

  const releaseWakeLock = async () => {
    const sentinel = wakeLock;
    wakeLock = null;
    try {
      if (sentinel && !sentinel.released) await sentinel.release();
    } catch (_) {}
  };

  const stopCurrentStream = async (targetStream = stream, releaseScreenLock = false) => {
    await stopMeter();
    if (targetStream) {
      try { targetStream.getTracks().forEach(track => track.stop()); } catch (_) {}
      if (stream === targetStream) stream = null;
    }
    if (releaseScreenLock) await releaseWakeLock();
  };

  const render = async () => {
    const recording = isRecording();
    const interrupted = !!active && active.phase === 'recording' && !recording;
    const finishing = !!active && active.phase === 'finishing';

    micButton.disabled = resumeBusy || finishing;
    micButton.setAttribute(
      'aria-label',
      recording ? 'Selesai merekam dan buat notulen'
        : interrupted ? 'Lanjutkan rekaman'
          : 'Mulai merekam'
    );
    micButton.title = recording ? 'Ketuk untuk berhenti dan buat notulen'
      : interrupted ? 'Ketuk untuk mengaktifkan mikrofon dan melanjutkan rekaman'
        : 'Ketuk untuk mulai merekam';

    $('mic-action-label').textContent = recording ? 'Ketuk mikrofon untuk berhenti'
      : interrupted ? (resumeBusy ? 'Mencoba mengaktifkan mikrofon kembali...' : 'Ketuk mikrofon untuk melanjutkan rekaman')
        : finishing ? 'Sedang sinkronisasi rekaman...'
          : 'Ketuk mikrofon untuk mulai';

    $('mic-circle').classList.toggle('recording', recording);
    $('record-badge').textContent = recording ? '● Merekam'
      : interrupted ? '⚠ Mic terputus'
        : finishing ? 'Sinkronisasi'
          : 'Siap merekam';

    $('record-state').textContent = recording
      ? 'Mikrofon aktif • layar dijaga tetap menyala bila browser mendukung'
      : interrupted
        ? 'Mikrofon tidak aktif • buka layar lalu ketuk mikrofon bila tidak pulih otomatis'
        : finishing
          ? 'Mengirim cadangan / menyiapkan notulen'
          : 'Mikrofon belum aktif';

    if (active) {
      const pending = (await allParts(active.uuid)).length;
      $('record-pending').textContent = 'Cadangan lokal: ' + pending;
      $('record-uploaded').textContent = String(Math.max(0, (active.nextSequence || 0) - pending));
    } else {
      $('record-pending').textContent = 'Cadangan lokal: 0';
    }
  };

  const stopTimer = () => {
    if (ticking) clearInterval(ticking);
    ticking = null;
  };

  const startTimer = () => {
    stopTimer();
    ticking = setInterval(() => {
      if (active?.started) $('record-timer').textContent = formatTime(dateStart() / 1000);
    }, 1000);
  };

  const queuePart = (evt, sourceRecorder = recorder) => {
    if (!evt.data || !evt.data.size || !active) return;
    const session = active, n = sequence++;
    session.nextSequence = sequence;
    const mime = sourceRecorder?.mimeType || evt.data.type || 'audio/webm';

    const promise = savePart(session.uuid, n, evt.data, mime)
      .then(() => saveState(session))
      .then(() => uploadPending())
      .catch(err => setError('Cadangan audio #' + n + ' gagal disimpan: ' + err.message));

    persistTasks.push(promise);
    promise.finally(() => {
      persistTasks = persistTasks.filter(p => p !== promise);
    });
  };

  const uploadPending = async () => {
    if (uploadBusy || !active) return;
    uploadBusy = true;
    const session = active;
    try {
      const pending = await allParts(session.uuid);
      for (const part of pending) {
        const form = new FormData();
        const ext = part.mime.includes('mp4') ? 'mp4' : (part.mime.includes('ogg') ? 'ogg' : 'webm');
        form.append('sequence', String(part.sequence));
        form.append('mime_type', part.mime);
        form.append('audio', part.blob, 'chunk-' + part.sequence + '.' + ext);
        try {
          await jsonCall(base + '/' + session.uuid + '/chunks', { method: 'POST', body: form });
          await removePart(part.key);
          clearError();
        } catch (err) {
          setError('Upload terhenti: ' + err.message + ' Audio yang belum terkirim tetap ada di perangkat dan akan dicoba lagi.');
          break;
        }
      }
      await render();
    } catch (err) {
      setError('Sinkronisasi gagal: ' + err.message);
    } finally {
      uploadBusy = false;
    }
  };

  const attemptFinish = async () => {
    if (!active || active.phase !== 'finishing' || finalizeBusy || uploadBusy) return;
    finalizeBusy = true;
    try {
      await uploadPending();
      const pending = await allParts(active.uuid);
      if (pending.length) {
        recoveryNotice.classList.remove('hidden');
        return;
      }
      if (!active.nextSequence) {
        setError('Belum ada audio yang berhasil direkam. Coba merekam ulang.');
        return;
      }

      const uuid = active.uuid;
      const result = await jsonCall(base + '/' + uuid + '/finish', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ expected_chunks: active.nextSequence }),
      });

      if (result.ok) {
        await clearState();
        active = null;
        stopTimer();
        recoveryNotice.classList.add('hidden');
        clearError();
        notify('Rekaman berhasil disimpan. Notulen sedang diproses oleh server.');
        window.location.assign(base + '/' + uuid);
      }
    } catch (err) {
      setError('Belum bisa menyelesaikan meeting: ' + err.message + ' Coba sinkronkan ulang.');
      recoveryNotice.classList.remove('hidden');
    } finally {
      finalizeBusy = false;
      await render();
    }
  };

  const chooseMime = () => {
    const supported = [
      'audio/webm;codecs=opus',
      'audio/mp4;codecs=mp4a.40.2',
      'audio/mp4',
      'audio/webm',
      'video/webm;codecs=vp8,opus',
    ];
    return supported.find(type => MediaRecorder.isTypeSupported(type)) || '';
  };

  const createMediaRecorder = audioStream => {
    const mime = chooseMime();
    return new MediaRecorder(
      audioStream,
      mime ? { mimeType: mime, audioBitsPerSecond: 64000 } : { audioBitsPerSecond: 64000 }
    );
  };

  const trackLooksHealthy = audioStream => {
    const tracks = audioStream?.getAudioTracks?.() || [];
    return tracks.some(track => track.readyState === 'live' && !track.muted);
  };

  const flushRecorder = () => {
    try {
      if (recorder?.state === 'recording') recorder.requestData();
    } catch (_) {}
  };

  const resumeRecording = async (userInitiated = false) => {
    if (!active || active.phase !== 'recording' || isRecording() || resumeBusy || manualStopRequested) return;

    resumeBusy = true;
    let acquiredStream = null;
    await render();

    try {
      acquiredStream = await navigator.mediaDevices.getUserMedia(audioConstraints);
      if (!active || active.phase !== 'recording' || manualStopRequested) {
        acquiredStream.getTracks().forEach(track => track.stop());
        acquiredStream = null;
        return;
      }
      const newStream = acquiredStream;
      const newRecorder = createMediaRecorder(newStream);

      stream = newStream;
      acquiredStream = null;
      recorder = newRecorder;
      active.interrupted = false;
      active.nextSequence = sequence;

      newRecorder.addEventListener('dataavailable', evt => queuePart(evt, newRecorder));
      newRecorder.addEventListener('error', e => {
        setError('Browser gagal merekam: ' + (e.error?.message || 'unknown'));
      });

      newRecorder.addEventListener('stop', async () => {
        if (
          manualStopRequested ||
          !active ||
          active.phase !== 'recording' ||
          recorder !== newRecorder
        ) return;

        await handleUnexpectedStop(newRecorder, newStream);
      });

      newStream.getAudioTracks().forEach(track => {
        track.addEventListener('ended', () => {
          if (recorder === newRecorder && newRecorder.state === 'recording' && !manualStopRequested) {
            try { newRecorder.stop(); } catch (_) {}
          }
        });

        track.addEventListener('mute', () => {
          if (recorder === newRecorder && active?.phase === 'recording') {
            setMeterLabel('Input mikrofon ditahan sementara oleh browser / sistem', 'MIC PAUSE', false, false);
          }
        });

        track.addEventListener('unmute', () => {
          if (recorder === newRecorder && active?.phase === 'recording') {
            setMeterLabel('Mikrofon aktif kembali', 'INPUT AKTIF', false, true);
          }
        });
      });

      newRecorder.start(15000);
      await saveState(active);
      await requestWakeLock(userInitiated);
      await startMeter(newStream);

      clearError();
      recoveryNotice.classList.add('hidden');
      notify(userInitiated
        ? 'Rekaman dilanjutkan. Mikrofon aktif kembali.'
        : 'Mikrofon aktif kembali dan rekaman dilanjutkan otomatis.');
    } catch (err) {
      if (acquiredStream) {
        try { acquiredStream.getTracks().forEach(track => track.stop()); } catch (_) {}
      }
      recorder = null;
      await stopCurrentStream(stream, false);
      setError(
        'Mikrofon sempat terputus dan belum bisa aktif kembali. ' +
        'Buka layar / kembali ke halaman ini lalu ketuk tombol mikrofon untuk melanjutkan. ' +
        'Audio selama mikrofon terputus mungkin tidak terekam. (' + err.message + ')'
      );
    } finally {
      resumeBusy = false;
      await render();
    }
  };

  async function handleUnexpectedStop(stoppedRecorder, stoppedStream) {
    if (unexpectedStopBusy || manualStopRequested || !active || active.phase !== 'recording') return;
    unexpectedStopBusy = true;
    let shouldResume = false;

    try {
      await Promise.allSettled(persistTasks);

      if (recorder === stoppedRecorder) recorder = null;
      await stopCurrentStream(stoppedStream, false);

      active.interrupted = true;
      active.nextSequence = sequence;
      await saveState(active);

      setError(
        'Mikrofon / browser menghentikan rekaman sementara. ' +
        'Bagian yang sudah direkam tetap disimpan. Saat layar aktif kembali sistem akan mencoba melanjutkan otomatis; ' +
        'jika belum aktif, ketuk tombol mikrofon. Audio ketika mikrofon dihentikan OS tidak dapat dipulihkan.'
      );
      recoveryNotice.classList.remove('hidden');
      $('recovery-text').textContent =
        'Mikrofon terputus. Sistem akan mencoba melanjutkan rekaman. Jika Anda ingin mengakhiri meeting dengan audio yang sudah tersimpan, gunakan tombol di bawah.';
      shouldResume = document.visibilityState === 'visible';
    } catch (err) {
      setError('Pemulihan mikrofon gagal: ' + err.message);
    } finally {
      unexpectedStopBusy = false;
      await render();
    }

    if (shouldResume) await resumeRecording(false);
  }

  const stopRecording = async () => {
    if (!recorder || recorder.state !== 'recording') return;
    micButton.disabled = true;
    manualStopRequested = true;
    const stoppingRecorder = recorder;

    try {
      flushRecorder();
      await new Promise(resolve => {
        stoppingRecorder.addEventListener('stop', resolve, { once: true });
        stoppingRecorder.stop();
      });

      await Promise.allSettled(persistTasks);
      await stopCurrentStream(stream, true);

      if (active) {
        active.phase = 'finishing';
        active.interrupted = false;
        active.nextSequence = sequence;
        await saveState(active);
      }

      recorder = null;
      await render();
      await attemptFinish();
    } catch (err) {
      setError('Gagal menghentikan rekaman: ' + err.message);
    } finally {
      recorder = null;
      manualStopRequested = false;
      await render();
    }
  };

  const startRecording = async () => {
    clearError();
    hideNotification();

    if (active) {
      setError('Selesaikan meeting sebelumnya sebelum mulai yang baru.');
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
      setError('Browser tidak mendukung perekaman. Gunakan Chrome Android/Safari terbaru melalui HTTPS atau localhost.');
      return;
    }

    micButton.disabled = true;
    let meetingCreated = false;
    let firstStream = null;

    try {
      firstStream = await navigator.mediaDevices.getUserMedia(audioConstraints);

      const meeting = await jsonCall(root.dataset.createUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: $('meeting-title').value }),
      });

      meetingCreated = true;
      sequence = 0;
      manualStopRequested = false;
      active = {
        uuid: meeting.uuid,
        title: $('meeting-title').value,
        started: Date.now(),
        nextSequence: 0,
        phase: 'recording',
        interrupted: false,
      };
      await saveState(active);

      stream = firstStream;
      firstStream = null;
      const recordingStream = stream;
      const newRecorder = createMediaRecorder(recordingStream);
      recorder = newRecorder;

      newRecorder.addEventListener('dataavailable', evt => queuePart(evt, newRecorder));
      newRecorder.addEventListener('error', e => {
        setError('Browser gagal merekam: ' + (e.error?.message || 'unknown'));
      });

      newRecorder.addEventListener('stop', async () => {
        if (
          manualStopRequested ||
          !active ||
          active.phase !== 'recording' ||
          recorder !== newRecorder
        ) return;

        await handleUnexpectedStop(newRecorder, recordingStream);
      });

      recordingStream.getAudioTracks().forEach(track => {
        track.addEventListener('ended', () => {
          if (recorder === newRecorder && newRecorder.state === 'recording' && !manualStopRequested) {
            try { newRecorder.stop(); } catch (_) {}
          }
        });

        track.addEventListener('mute', () => {
          if (recorder === newRecorder && active?.phase === 'recording') {
            setMeterLabel('Input mikrofon ditahan sementara oleh browser / sistem', 'MIC PAUSE', false, false);
          }
        });

        track.addEventListener('unmute', () => {
          if (recorder === newRecorder && active?.phase === 'recording') {
            setMeterLabel('Mikrofon aktif kembali', 'INPUT AKTIF', false, true);
          }
        });
      });

      newRecorder.start(15000);

      await requestWakeLock(true);
      await startMeter(recordingStream);

      $('record-timer').textContent = '00:00:00';
      startTimer();
      await render();

      notify(
        navigator.wakeLock?.request
          ? 'Rekaman aktif. Sistem akan menjaga layar tetap menyala selama browser mengizinkan.'
          : 'Rekaman aktif. Browser ini tidak mendukung Screen Wake Lock; matikan auto-lock layar selama meeting.'
      );
    } catch (err) {
      if (firstStream) {
        try { firstStream.getTracks().forEach(track => track.stop()); } catch (_) {}
      }
      await stopCurrentStream(stream, true);
      setError((meetingCreated ? 'Meeting sudah dibuat, tetapi perekaman gagal. ' : '') + err.message);

      if (!meetingCreated) {
        recorder = null;
      } else if (active?.phase === 'recording') {
        active.interrupted = true;
        active.nextSequence = sequence;
        try { await saveState(active); } catch (_) {}
      }
    } finally {
      await render();
    }
  };

  const ensureCaptureAfterForeground = async () => {
    if (!active || active.phase !== 'recording' || manualStopRequested) return;

    await requestWakeLock(false);

    try {
      if (meterContext?.state === 'suspended') await meterContext.resume();
    } catch (_) {}

    if (!isRecording()) {
      await resumeRecording(false);
      return;
    }

    setTimeout(async () => {
      if (!active || active.phase !== 'recording' || manualStopRequested || !isRecording()) return;
      if (trackLooksHealthy(stream)) return;

      const unhealthyRecorder = recorder;
      try { unhealthyRecorder.requestData(); } catch (_) {}
      try { unhealthyRecorder.stop(); }
      catch (_) {
        if (recorder === unhealthyRecorder) recorder = null;
        await resumeRecording(false);
      }
    }, 700);
  };

  const recover = async () => {
    try {
      if (!active) active = await getState();
      if (!active) return;

      if (active.phase === 'recording') {
        active.phase = 'finishing';
        active.interrupted = false;
        await saveState(active);
      }

      recoveryNotice.classList.remove('hidden');
      recoverButton.disabled = true;
      await uploadPending();
      await attemptFinish();
    } catch (e) {
      setError('Pemulihan gagal: ' + e.message);
    } finally {
      recoverButton.disabled = false;
    }
  };

  const init = async () => {
    try {
      active = await getState();
      if (active) {
        sequence = active.nextSequence || 0;
        recoveryNotice.classList.remove('hidden');
        $('recovery-text').textContent =
          'Ditemukan meeting "' + (active.title || 'Tanpa judul') +
          '". Rekaman yang sudah tersimpan di perangkat akan dikirim ulang. ' +
          'Bagian yang tidak sempat tersimpan tidak dapat dipulihkan.';
        active.phase = 'finishing';
        active.interrupted = false;
        await saveState(active);
        await render();
        await recover();
      } else {
        await render();
      }
    } catch (e) {
      setError('Tidak bisa membaca penyimpanan lokal: ' + e.message);
      micButton.disabled = true;
    }

    setInterval(async () => {
      if (!active) return;
      await uploadPending();
      if (active?.phase === 'finishing') await attemptFinish();
    }, 8000);
  };

  micButton.addEventListener('click', () => {
    if (isRecording()) {
      stopRecording();
    } else if (active?.phase === 'recording') {
      resumeRecording(true);
    } else if (!active) {
      startRecording();
    }
  });

  recoverButton.addEventListener('click', recover);

  window.addEventListener('online', async () => {
    if (!active) return;
    await uploadPending();
    if (active?.phase === 'finishing') await attemptFinish();
  });

  window.addEventListener('beforeunload', e => {
    if (isRecording()) {
      flushRecorder();
      e.preventDefault();
      e.returnValue = 'Rekaman sedang berlangsung.';
    }
  });

  window.addEventListener('pagehide', () => {
    flushRecorder();
  });

  document.addEventListener('visibilitychange', async () => {
    if (document.hidden) {
      if (isRecording()) {
        flushRecorder();
        if (active) {
          active.lastBackgroundAt = Date.now();
          try { await saveState(active); } catch (_) {}
        }
      }
      return;
    }

    await ensureCaptureAfterForeground();
  });

  drawMeter();
  init();
})();
