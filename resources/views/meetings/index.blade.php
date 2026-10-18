@extends('components.layout')
@section('title','Dashboard')
@section('content')
<div class="hero"><div><div class="eyebrow">MEETING INTELLIGENCE</div><h1>Rapat selesai, <em>notulen siap.</em></h1><p>Ketuk mikrofon untuk mulai atau berhenti merekam. Audio disimpan bertahap, lalu sistem otomatis memproses notulen.</p></div><div class="hero-graphic"><span>◉</span><div class="hero-bars">▂ ▅ ▇ ▄ ▆ ▂ ▄ ▇ ▅</div></div></div>
@if($demo)<div class="alert warning"><b>Mode demo aktif.</b> Notulen adalah simulasi dan tidak berasal dari transkrip asli. Matikan <code>MEETING_DEMO_AI</code> untuk hasil nyata.</div>@endif
<div class="grid-main">
<section class="panel recorder-card" id="recorder-root" data-create-url="{{ route('meetings.store') }}" data-url-base="{{ url('/meetings') }}">
<div class="panel-title"><span class="panel-bullet">01</span><div><h2>Rekam meeting</h2><p>Ketuk ikon mikrofon untuk mulai / berhenti</p></div><span id="record-badge" class="pill">Siap merekam</span></div>
<label class="field-label" for="meeting-title">Judul meeting</label><input id="meeting-title" placeholder="Contoh: Weekly Meeting Operational" maxlength="180" autocomplete="off">
<div class="recording-zone">
    <button type="button" class="mic-outer mic-button" id="mic-circle" aria-label="Mulai merekam" title="Ketuk untuk mulai merekam">
        <span class="mic-inner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/></svg></span>
    </button>
    <div id="mic-action-label" class="mic-action-label">Ketuk mikrofon untuk mulai</div>
    <div class="audio-meter" aria-label="Visualisasi level audio mikrofon secara langsung">
        <div class="audio-meter-header"><span><span id="mic-signal-dot" class="audio-meter-dot"></span><span id="mic-signal-label" aria-live="polite">Mikrofon belum aktif</span></span><span id="mic-level-label" class="audio-level-label">--</span></div>
        <canvas id="mic-waveform" width="640" height="100" role="img" aria-label="Grafik aktivitas input mikrofon"></canvas>
        <div class="audio-meter-foot">Grafik bergerak sesuai suara yang diterima mikrofon</div>
    </div>
    <div class="timer" id="record-timer">00:00:00</div><div class="muted" id="record-state">Mikrofon belum aktif</div>
</div>
<div class="upload-info"><div><span id="record-uploaded">0</span> bagian tersimpan di server</div><div id="record-pending">Cadangan lokal: 0</div></div>
<div class="alert warning hidden" id="recovery-notice"><b>Rekaman sebelumnya ditemukan.</b><p id="recovery-text">Potongan yang sempat tersimpan di perangkat akan dikirim ulang.</p><button class="button secondary" id="recover-button">Sinkronkan &amp; Selesaikan</button></div>
<div class="alert error hidden" id="recorder-error"></div><div class="alert success hidden" id="recorder-success"></div>
<p class="muted mini">Biarkan halaman aktif dan layar HP menyala selama merekam. Setelah berhenti Anda otomatis diarahkan ke halaman progres. HTTPS diperlukan untuk akses mikrofon dari HP lain.</p>
</section>
<aside class="stack aside"><section class="panel feature-card"><div class="mini-icon amber">☁</div><h3>Backup bertahap</h3><p>Potongan audio dikirim ke server sekitar setiap 15 detik dan diupload ke Google Drive melalui antrean.</p></section><section class="panel feature-card"><div class="mini-icon blue">✧</div><h3>Status terpantau</h3><p>Lihat jumlah antrean, jumlah audio yang sudah masuk Drive, tahap transkripsi, PDF, dan email.</p></section><section class="panel feature-card"><div class="mini-icon green">✉</div><h3>PDF &amp; email</h3><p>Hasil ditujukan ke <b>{{ config('meeting.initial_recipient') }}</b> ketika pengiriman email diaktifkan.</p><p class="mini muted">Pengiriman: {{ config('meeting.mail_enabled') ? 'aktif' : 'nonaktif' }}</p></section></aside>
</div>
@if($historyAccess)
<section class="panel list-panel"><div class="section-heading"><div><div class="eyebrow">RIWAYAT MEETING</div><h2>Rekaman terbaru</h2></div><span class="muted mini">{{ $meetings->total() }} meeting</span></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Meeting</th><th>Tanggal</th><th>Status</th><th>Dokumen</th></tr></thead><tbody>
@forelse($meetings as $meeting)
<tr><td><a class="table-link" href="{{ route('meetings.show',$meeting) }}">{{ $meeting->title }}</a><div class="mini muted">{{ Str::limit($meeting->uuid,13) }}</div></td><td>{{ $meeting->started_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td><td><span class="state state-{{ $meeting->status }}">{{ ucfirst($meeting->status) }}</span><div class="mini muted">{{ \App\Support\MeetingProgress::label($meeting->process_stage) }}</div></td><td>@if($meeting->document_path && str_ends_with(strtolower($meeting->document_path), '.pdf'))<a href="{{ route('meetings.pdf',$meeting) }}">↓ PDF</a>@else<span class="muted">—</span>@endif</td></tr>
@empty <tr><td colspan="4" class="muted">Belum ada meeting. Silakan mulai merekam.</td></tr> @endforelse
</tbody></table></div><div class="pagination">{{ $meetings->links('pagination::simple-default') }}</div></section>
@else
<div class="alert warning">Akun Anda dapat merekam meeting. Riwayat meeting seluruh pengguna hanya tersedia bagi akun yang diberikan izin History. Hasil meeting yang baru Anda rekam tetap dapat diakses setelah berhenti.</div>
@endif
<script defer src="{{ asset('js/recorder.js') }}?v=20261018pdf"></script>
@endsection
