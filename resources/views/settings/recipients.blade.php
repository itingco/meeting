@extends('components.layout')
@section('title','Pengaturan Penerima')
@section('content')
<div class="page-head"><div><a class="backlink" href="{{ route('meetings.index') }}">← Dashboard</a><div class="eyebrow">ADMINISTRASI</div><h1>Pengaturan & Integrasi</h1><p class="muted">Siapkan penerima departemen dan hubungkan Google Drive.</p></div></div>
<div class="settings-grid">
<section class="panel settings-panel"><div class="section-heading"><div><h2>Email departemen</h2><p class="muted">Fitur disiapkan, belum digunakan.</p></div><span class="state state-failed">Routing Nonaktif</span></div>
<div class="alert warning">Saat ini hasil hanya diarahkan ke <b>{{ config('meeting.initial_recipient') }}</b> ketika email diaktifkan. Data di bawah hanya tersimpan, tidak dikirim sampai <code>MEETING_RECIPIENT_ROUTING_ENABLED=true</code>.</div>
<form method="post" action="{{ route('settings.recipients.save') }}">@csrf
<div class="recipient-list">@foreach($recipients as $recipient)<div class="recipient-row"><div><strong>{{ $recipient->department }}</strong><div class="muted mini">{{ $recipient->is_executive ? 'Wajib terkirim saat routing diaktifkan' : 'Opsional saat routing diaktifkan' }}</div></div><input type="email" name="recipients[{{ $recipient->id }}][email]" placeholder="alamat@ingco.co.id" value="{{ $recipient->email }}">@if($recipient->is_executive)<span class="pill">Wajib</span>@else<label class="checkbox-label"><input type="checkbox" name="recipients[{{ $recipient->id }}][enabled]" value="1" {{ $recipient->enabled ? 'checked' : '' }}> Pilih</label>@endif</div>@endforeach</div>
<button class="button primary">Simpan Penerima</button>
</form></section>
<aside class="stack"><div class="panel feature-card"><div class="mini-icon blue">☁</div><h3>Google Drive OAuth</h3><p>Status: <b>{{ $driveConnected ? 'Terhubung' : 'Belum terhubung' }}</b></p><p class="mini muted">Backup: {{ config('meeting.drive_enabled') ? 'aktif' : 'nonaktif' }}</p>
@if($driveConnected)<form method="post" action="{{ route('settings.google.disconnect') }}">@csrf<button class="button secondary">Putuskan koneksi</button></form>@else<a class="button primary" href="{{ route('settings.google.connect') }}">Hubungkan Google Drive</a>@endif
<p class="mini muted">Akses memerlukan OAuth Client ID, Client Secret, dan folder Drive yang dapat diakses akun Google.</p></div>
<div class="panel feature-card"><div class="mini-icon amber">✉</div><h3>SMTP Notulen</h3><p>Pengirim: <b>{{ config('mail.from.address') }}</b></p><p class="muted mini">Host: {{ config('mail.mailers.smtp.host') }} • Port {{ config('mail.mailers.smtp.port') }}</p><p class="muted mini">Pengiriman: {{ config('meeting.mail_enabled') ? 'AKTIF' : 'NONAKTIF' }}. Isi password hanya dalam file .env.</p></div></aside>
</div>
@endsection
