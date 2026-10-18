<!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#142a42">
<title>@yield('title','Meeting AI') • INGCO</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=20261018pdf">
</head><body>
<div class="shell">
<header class="topbar">
    <a class="brand" href="{{ route('meetings.index') }}"><span class="brand-icon">✦</span><span>INGCO <b>MEETING AI</b><small>SMART MINUTES SYSTEM</small></span></a>
    @auth
    <nav class="topnav" aria-label="Navigasi">
        <a href="{{ route('meetings.index') }}">Rekam</a>
        @can('manage-settings')<a href="{{ route('settings.recipients') }}">Pengaturan</a>@endcan
        @can('manage-users')<a href="{{ route('users.index') }}">User</a>@endcan
        <a href="{{ route('profile.password') }}">Password</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="link-btn">Keluar</button></form>
    </nav>
    @endauth
</header>
<main class="container">
    @if(session('message'))<div class="alert success">{{ session('message') }}</div>@endif
    @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
<footer class="footer">© {{ date('Y') }} Dept. IT INGCO INDONESIA • Akses internal</footer>
</div>
</body></html>
