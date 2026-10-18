@extends('components.layout')
@section('title', 'Login')
@section('content')
<div class="login-box panel">
<div class="eyebrow">AKSES INTERNAL</div>
<h1>Masuk ke Meeting AI</h1><p class="muted">Rekam rapat, simpan audio, dan hasilkan notulen otomatis.</p>
<form method="post" action="{{ route('login') }}" class="stack">@csrf
<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
<button class="button primary" type="submit">Masuk <span>→</span></button></form>
<p class="muted mini">Akun administrator dibuat melalui perintah <code>php artisan meeting:admin</code>.</p>
</div>
@endsection
