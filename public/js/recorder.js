/* INGCO Meeting AI: browser-side crash/reconnect recovery via IndexedDB.
   Browser background audio is NOT guaranteed (notably iOS). */
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
  const dateStart = () => active?.started ? Date.now() - active.started : 0;

  const openDb = () => new Promise((resolve, reject) => {
    if (!window.indexedDB) { reject(new Error('Browser ini tidak mendukung IndexedDB untuk cadangan lokal.')); return; }
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
  const savePart = (uuid, n, blob, mime) => transact('parts', 'readwrite', s => s.put({ key: uuid + ':' + n, meetingId: uuid, sequence: n, blob, mime }));
  const removePart = key => transact('parts', 'readwrite', s => s.delete(key));
  const allParts = async uuid => {
    const parts = await requestStore('parts', s => s.index('meetingId').getAll(uuid));
    return parts.sort((a,b) => a.sequence - b.sequence);
  };
  const setError = message => { $('recorder-error').textContent = message; $('recorder-error').classList.remove('hidden'); };
  const clearError = () => $('recorder-error').classList.add('hidden');
  const notify = message => { $('recorder-success').textContent = message; $('recorder-success').classList.remove('hidden'); };
  const hideNotification = () => $('recorder-success').classList.add('hidden');
  const formatTime = seconds => { const s = Math.max(0, Math.floor(seconds)); return [Math.floor(s/3600), Math.floor(s/60)%60, s%60].map(x=>String(x).padStart(2,'0')).join(':'); };
  const jsonCall = async (url, options={}) => {
    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', ...options.headers };
    const res = await fetch(url, { credentials: 'same-origin', ...options, headers });
    const text = await res.text(); let obj;
    try { obj = JSON.parse(text); } catch (e) { throw new Error('Respons server bukan JSON. Cek login, APP_DEBUG dan laravel.log (HTTP '+res.status+').'); }
    if (!res.ok) {
      const message = obj.error || obj.message || (obj.errors ? Object.values(obj.errors).flat().join('; ') : null);
      throw new Error(message || 'Server gagal dengan HTTP '+res.status);
    }
    return obj;
  };
  const render = async () => {
    const isRecording = !!recorder && recorder.state === 'recording';
    micButton.disabled = !!active && !isRecording;
    micButton.setAttribute('aria-label', isRecording ? 'Selesai merekam dan buat notulen' : 'Mulai merekam');
    micButton.title = isRecording ? 'Ketuk untuk berhenti dan buat notulen' : 'Ketuk untuk mulai merekam';
    $('mic-action-label').textContent = isRecording ? 'Ketuk mikrofon untuk berhenti' : active ? 'Sedang sinkronisasi rekaman...' : 'Ketuk mikrofon untuk mulai';
    $('mic-circle').classList.toggle('recording', isRecording);
    $('record-badge').textContent = isRecording ? '● Merekam' : active ? 'Sinkronisasi' : 'Siap merekam';
    $('record-state').textContent = isRecording ? 'Mikrofon aktif • jangan tutup halaman' : active ? 'Mengirim cadangan / menyiapkan notulen' : 'Mikrofon belum aktif';
    if (active) {
      const pending = (await allParts(active.uuid)).length;
      $('record-pending').textContent = 'Cadangan lokal: '+pending;
      $('record-uploaded').textContent = String(Math.max(0, (active.nextSequence||0) - pending));
    } else {
      $('record-pending').textContent = 'Cadangan lokal: 0';
    }
  };
  const stopTimer = () => { if (ticking) clearInterval(ticking); ticking = null; };
  const startTimer = () => {
    stopTimer();
    ticking = setInterval(() => { if (active?.started) $('record-timer').textContent = formatTime(dateStart()/1000); },1000);
  };
  const releaseMic = async () => {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    try { if (wakeLock) await wakeLock.release(); } catch (e) {}
    wakeLock = null;
  };
  const queuePart = evt => {
    if (!evt.data || !evt.data.size || !active) return;
    const session = active, n = sequence++;
    session.nextSequence = sequence;
    const mime = recorder?.mimeType || evt.data.type || 'audio/webm';
    const promise = savePart(session.uuid,n,evt.data,mime)
      .then(() => saveState(session))
      .then(() => uploadPending())
      .catch(err => setError('Cadangan audio #'+n+' gagal disimpan: '+err.message));
    persistTasks.push(promise);
    promise.finally(() => { persistTasks = persistTasks.filter(p => p !== promise); });
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
        form.append('sequence',String(part.sequence));
        form.append('mime_type',part.mime);
        form.append('audio',part.blob,'chunk-'+part.sequence+'.'+ext);
        try {
          await jsonCall(base+'/'+session.uuid+'/chunks', { method:'POST',body:form });
          await removePart(part.key);
          clearError();
        } catch (err) {
          setError('Upload terhenti: '+err.message+' Audio yang belum terkirim tetap ada di perangkat dan akan dicoba lagi.');
          break;
        }
      }
      await render();
    } catch (err) { setError('Sinkronisasi gagal: '+err.message); }
    finally { uploadBusy = false; }
  };
  const attemptFinish = async () => {
    if (!active || active.phase !== 'finishing' || finalizeBusy || uploadBusy) return;
    finalizeBusy = true;
    try {
      await uploadPending();
      const pending = await allParts(active.uuid);
      if (pending.length) { recoveryNotice.classList.remove('hidden'); return; }
      if (!active.nextSequence) { setError('Belum ada audio yang berhasil direkam. Coba merekam ulang.'); return; }
      const uuid = active.uuid;
      const result = await jsonCall(base+'/'+uuid+'/finish',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({expected_chunks:active.nextSequence})});
      if (result.ok) {
        await clearState();
        active = null;
        stopTimer(); recoveryNotice.classList.add('hidden'); clearError();
        notify('Rekaman berhasil disimpan. Notulen sedang diproses oleh server.');
        window.location.assign(base+'/'+uuid);
      }
    } catch(err) { setError('Belum bisa menyelesaikan meeting: '+err.message+' Coba sinkronkan ulang.'); recoveryNotice.classList.remove('hidden'); }
    finally { finalizeBusy = false; await render(); }
  };
  const stopRecording = async () => {
    if (!recorder || recorder.state !== 'recording') return;
    micButton.disabled = true;
    manualStopRequested = true;
    try {
      await new Promise(resolve => {
        recorder.addEventListener('stop', resolve, { once:true });
        recorder.stop();
      });
      await Promise.allSettled(persistTasks);
      await releaseMic();
      if (active) { active.phase = 'finishing'; active.nextSequence = sequence; await saveState(active); }
      await render();
      await attemptFinish();
    } catch(err) { setError('Gagal menghentikan rekaman: '+err.message); }
    finally { recorder = null; manualStopRequested = false; await render(); }
  };
  const chooseMime = () => {
    const supported = ['audio/webm;codecs=opus','audio/mp4;codecs=mp4a.40.2','audio/mp4','audio/webm','video/webm;codecs=vp8,opus'];
    return supported.find(type => MediaRecorder.isTypeSupported(type)) || '';
  };
  const startRecording = async () => {
    clearError(); hideNotification();
    if (active) { setError('Selesaikan meeting sebelumnya sebelum mulai yang baru.'); return; }
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) { setError('Browser tidak mendukung perekaman. Gunakan Chrome Android/Safari terbaru melalui HTTPS atau localhost.'); return; }
    micButton.disabled = true;
    let meetingCreated = false;
    try {
      stream = await navigator.mediaDevices.getUserMedia({video:false,audio:{echoCancellation:true,noiseSuppression:true,channelCount:1}});
      const mime = chooseMime();
      recorder = new MediaRecorder(stream, mime ? {mimeType:mime,audioBitsPerSecond:64000} : {audioBitsPerSecond:64000});
      const meeting = await jsonCall(root.dataset.createUrl, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({title:$('meeting-title').value})});
      meetingCreated = true;
      sequence=0; manualStopRequested=false;
      active = {uuid:meeting.uuid,title:$('meeting-title').value,started:Date.now(),nextSequence:0,phase:'recording'};
      await saveState(active);
      recorder.addEventListener('dataavailable',queuePart);
      recorder.addEventListener('error', e => setError('Browser gagal merekam: '+(e.error?.message||'unknown')));
      recorder.addEventListener('stop', async () => {
        if (!manualStopRequested && active?.phase === 'recording') {
          setError('Mikrofon / browser menghentikan rekaman. Menyimpan bagian yang sudah ada.');
          await Promise.allSettled(persistTasks);
          await releaseMic();
          active.phase = 'finishing'; active.nextSequence = sequence;
          try { await saveState(active); await render(); await attemptFinish(); }
          catch(e) { setError('Pemulihan otomatis gagal: '+e.message); }
        }
      });
      stream.getAudioTracks().forEach(track => track.addEventListener('ended', () => {
        if (recorder?.state === 'recording') recorder.stop();
      }));
      recorder.start(15000);
      try { if (navigator.wakeLock) wakeLock=await navigator.wakeLock.request('screen'); } catch(e) {}
      $('record-timer').textContent='00:00:00'; startTimer(); await render();
      notify('Rekaman aktif. Pertahankan halaman ini terbuka dan layar tetap menyala.');
    } catch(err) {
      await releaseMic();
      setError((meetingCreated ? 'Meeting sudah dibuat, tetapi perekaman gagal. ' : '')+err.message);
      if (!meetingCreated) recorder=null;
    } finally { await render(); }
  };
  const recover = async () => {
    try {
      if (!active) active=await getState();
      if (!active) return;
      if (active.phase==='recording') {
        active.phase='finishing';
        await saveState(active);
      }
      recoveryNotice.classList.remove('hidden');
      recoverButton.disabled = true;
      await uploadPending();
      await attemptFinish();
    } catch(e) { setError('Pemulihan gagal: '+e.message); }
    finally { recoverButton.disabled = false; }
  };
  const init = async () => {
    try {
      active = await getState();
      if (active) {
        sequence = active.nextSequence||0;
        recoveryNotice.classList.remove('hidden');
        $('recovery-text').textContent='Ditemukan meeting "'+(active.title||'Tanpa judul')+'". Rekaman yang sudah tersimpan di perangkat akan dikirim ulang. Bagian yang tidak sempat tersimpan tidak dapat dipulihkan.';
        active.phase='finishing'; await saveState(active);
        await render(); await recover();
      } else await render();
    } catch(e) { setError('Tidak bisa membaca penyimpanan lokal: '+e.message); micButton.disabled = true; }
    setInterval(async()=>{ if (!active) return; await uploadPending(); if (active?.phase==='finishing') await attemptFinish(); },8000);
  };
  micButton.addEventListener('click', () => {
    if (recorder?.state === 'recording') stopRecording();
    else if (!active) startRecording();
  });
  recoverButton.addEventListener('click', recover);
  window.addEventListener('online', async()=>{ if(active) { await uploadPending(); await attemptFinish(); }});
  window.addEventListener('beforeunload', e=>{ if(recorder?.state==='recording'){e.preventDefault();e.returnValue='Rekaman sedang berlangsung.';} });
  document.addEventListener('visibilitychange', ()=>{
    if (document.hidden && recorder?.state==='recording') notify('Peringatan: browser mobile dapat menghentikan rekaman di background.');
  });
  init();
})();
