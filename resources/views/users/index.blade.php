@extends('components.layout')
@section('title','Manajemen User')
@section('content')
<div class="page-head"><div><a class="backlink" href="{{ route('meetings.index') }}">← Dashboard</a><div class="eyebrow">AKSES APLIKASI</div><h1>Manajemen User</h1><p class="muted">Administrator dapat mengatur akses riwayat meeting dan pengaturan secara terpisah.</p></div></div>
<div class="panel settings-panel">
<h2>Tambah user</h2><form method="post" action="{{ route('users.store') }}" class="user-form">@csrf
<div class="user-grid"><label>Nama<input name="name" required value="{{ old('name') }}"></label><label>Email<input type="email" name="email" required value="{{ old('email') }}"></label><label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><label>Konfirmasi password<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label></div>
<div class="user-flags"><label class="checkbox-label"><input type="checkbox" name="can_view_history" value="1"> Akses History (semua meeting)</label><label class="checkbox-label"><input type="checkbox" name="can_manage_settings" value="1"> Akses Pengaturan</label><label class="checkbox-label"><input type="checkbox" name="is_admin" value="1"> Administrator / kelola user</label></div>
<button class="button primary">+ Tambah User</button></form>
</div>
<div class="panel list-panel"><h2>User terdaftar</h2><p class="muted mini">Akun tanpa akses History tetap dapat merekam dan melihat progres meeting miliknya sendiri.</p>
@foreach($users as $user)
<details class="user-edit" {{ $loop->first ? 'open' : '' }}><summary><b>{{ $user->name }}</b> <span class="muted mini">{{ $user->email }} · {{ $user->is_admin ? 'Admin' : 'User' }} · {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></summary>
<form method="post" action="{{ route('users.update',$user) }}">@csrf @method('PUT')
<div class="user-grid"><label>Nama<input name="name" value="{{ $user->name }}" required></label><label>Email<input name="email" type="email" value="{{ $user->email }}" required></label><label>Password baru (opsional)<input type="password" name="password" minlength="10" autocomplete="new-password"></label><label>Konfirmasi password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password"></label></div>
<div class="user-flags"><label class="checkbox-label"><input type="checkbox" name="can_view_history" value="1" {{ $user->can_view_history?'checked':'' }}> History</label><label class="checkbox-label"><input type="checkbox" name="can_manage_settings" value="1" {{ $user->can_manage_settings?'checked':'' }}> Pengaturan</label><label class="checkbox-label"><input type="checkbox" name="is_admin" value="1" {{ $user->is_admin?'checked':'' }}> Administrator</label><label class="checkbox-label"><input type="checkbox" name="is_active" value="1" {{ $user->is_active?'checked':'' }}> User aktif</label></div>
<button class="button secondary">Simpan Perubahan</button></form>
</details>
@endforeach
</div>
@endsection
