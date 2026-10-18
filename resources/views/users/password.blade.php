@extends('components.layout')
@section('title','Ubah Password')
@section('content')
<div class="page-head"><div><a class="backlink" href="{{ route('meetings.index') }}">← Dashboard</a><div class="eyebrow">AKUN SAYA</div><h1>Ubah Password</h1><p class="muted">Anda bisa mengganti password sendiri tanpa bantuan administrator.</p></div></div>
<div class="panel settings-panel" style="max-width:560px"><form method="post" action="{{ route('profile.password.save') }}">@csrf
<div class="password-fields"><label>Password sekarang<input type="password" name="current_password" required autocomplete="current-password"></label><label>Password baru (minimal 10 karakter)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><label>Konfirmasi password baru<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label></div>
<button class="button primary">Simpan Password</button></form></div>
@endsection
