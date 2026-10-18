<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sales Dashboard')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --yellow: #ffc000;
            --yellow-soft: #fff2cc;
            --red: #c00000;
            --green: #008a2e;
            --blue: #4472c4;
            --navy: #17365d;
            --page: #f3f5f8;
            --line: #1e1e1e;
            --muted: #667085;
            --sidebar-width: 270px;
            --sidebar-collapsed-width: 78px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page);
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
        }
        .page-shell { max-width: 1920px; margin: 0 auto; }
        .filter-panel, .chart-panel {
            background: #fff;
            border: 1px solid #d0d5dd;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(16, 24, 40, .06);
        }
        .form-label { font-size: .8rem; margin-bottom: .35rem; }
        .btn-report { background: var(--yellow); border-color: #d49e00; color: #111; font-weight: 800; }
        .btn-report:hover { background: #eeb300; border-color: #bd8e00; color: #111; }
        .period-box {
            background: #fff8dd; border: 1px solid #f2d46b; padding: .65rem .85rem;
            border-radius: 8px; font-size: .8rem; line-height: 1.45;
        }
        .report-table-wrap {
            width: 100%; overflow: auto; background: #fff;
            border: 1px solid #111; max-height: 72vh;
        }
        .report-table {
            width: 100%; min-width: 1500px; border-collapse: collapse;
            font-size: 12px; line-height: 1.15; font-variant-numeric: tabular-nums;
        }
        .report-table th, .report-table td { border: 1px solid #111; padding: 4px 5px; white-space: nowrap; }
        .report-table thead th {
            position: sticky; top: 0; z-index: 5; background: var(--yellow); color: #111;
            font-family: Georgia, 'Times New Roman', serif; font-weight: 800;
            text-align: center; vertical-align: middle;
        }
        .report-table thead tr:nth-child(2) th { top: 42px; }
        .report-table .line-cell, .report-table .line-head {
            position: sticky; left: 0; z-index: 4; background: #fff; font-weight: 800;
        }
        .report-table .line-head { z-index: 8; background: var(--yellow); }
        .report-table tbody tr:hover td { background: #fff8dc; }
        .report-table tbody tr:hover .line-cell { background: #fff8dc; }
        .report-table .number { text-align: right; }
        .report-table .center { text-align: center; }
        .report-table .growth-up { color: var(--green); font-weight: 800; }
        .report-table .growth-down { color: var(--red); font-weight: 800; }
        .report-table .growth-empty { color: #667085; font-weight: 700; }
        .report-table .achievement-cell { background: var(--red); color: #fff; text-align: center; font-weight: 900; }
        .report-table .achievement-cell.good { background: #008a2e; }
        .report-table .achievement-cell.warn { background: #ed7d31; }
        .report-table .total-row td { background: var(--yellow) !important; color: #111 !important; font-weight: 900; }
        .report-table .total-row .line-cell { background: var(--yellow) !important; }
        .report-caption {
            background: #fff; border: 1px solid #d0d5dd; border-bottom: 0;
            border-radius: 10px 10px 0 0; padding: .75rem 1rem;
        }
        .status-pill {
            display: inline-flex; align-items: center; border-radius: 999px;
            padding: .28rem .6rem; background: #fff2cc; border: 1px solid #e9c548;
            font-size: .72rem; font-weight: 800;
        }
        .chart-panel { padding: 1rem; height: 100%; }
        .chart-panel canvas { width: 100%; display: block; }
        .chart-title { font-size: .95rem; font-weight: 800; margin-bottom: .15rem; color: #101828; }
        .chart-subtitle { font-size: .75rem; color: var(--muted); margin-bottom: .75rem; }
        .empty-state { padding: 3rem 1rem; text-align: center; color: var(--muted); }
        .kpi-status-good { background: #dcfce7 !important; color: #166534 !important; border-color: #86efac !important; }
        .kpi-status-warning { background: #fef3c7 !important; color: #92400e !important; border-color: #fcd34d !important; }
        .kpi-status-bad { background: #fee2e2 !important; color: #991b1b !important; border-color: #fca5a5 !important; }
        .kpi-status-na { background: #f2f4f7 !important; color: #475467 !important; border-color: #d0d5dd !important; }

        /* Sidebar */
        .app-sidebar {
            position: fixed; inset: 0 auto 0 0; width: var(--sidebar-width);
            z-index: 1040; display: flex; flex-direction: column;
            background: #101828; color: #fff;
            border-right: 1px solid rgba(255,255,255,.08);
            box-shadow: 8px 0 28px rgba(16,24,40,.12);
            transition: width .22s ease, transform .22s ease;
        }
        .sidebar-brand {
            min-height: 74px; display: flex; align-items: center; gap: .75rem;
            padding: .9rem .85rem; border-bottom: 3px solid var(--yellow);
        }
        .brand-mark {
            flex: 0 0 44px; width: 44px; height: 44px; display: grid;
            place-items: center; background: var(--yellow); color: #111;
            font-weight: 900; border-radius: 10px; box-shadow: 0 5px 15px rgba(255,192,0,.18);
        }
        .brand-text { min-width: 0; flex: 1; line-height: 1.15; }
        .brand-title { font-weight: 900; font-size: .95rem; white-space: nowrap; }
        .brand-subtitle { color: #98a2b3; font-size: .68rem; margin-top: .25rem; white-space: nowrap; }
        .sidebar-toggle {
            flex: 0 0 34px; width: 34px; height: 34px;
            border: 1px solid rgba(255,255,255,.14); border-radius: 8px;
            display: grid; place-items: center; background: rgba(255,255,255,.05); color: #fff;
        }
        .sidebar-toggle:hover { background: rgba(255,192,0,.16); border-color: rgba(255,192,0,.4); color: var(--yellow); }
        .sidebar-scroll { flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: .8rem .7rem 1rem; }
        .sidebar-section-title {
            color: #667085; font-size: .65rem; letter-spacing: .11em;
            font-weight: 900; padding: .65rem .65rem .35rem; white-space: nowrap;
        }
        .sidebar-nav { display: grid; gap: .3rem; }
        .sidebar-link {
            min-height: 44px; display: flex; align-items: center; gap: .78rem; color: #d0d5dd;
            text-decoration: none; border-radius: 9px; padding: .65rem .72rem;
            font-size: .82rem; font-weight: 700;
            transition: background .15s ease, color .15s ease, transform .15s ease;
            white-space: nowrap;
        }
        .sidebar-link:hover { background: rgba(255,255,255,.07); color: #fff; transform: translateX(2px); }
        .sidebar-link.active { background: var(--yellow); color: #111; font-weight: 900; box-shadow: 0 5px 16px rgba(255,192,0,.18); }
        .sidebar-icon { flex: 0 0 25px; width: 25px; text-align: center; font-size: 1.12rem; line-height: 1; }
        .nav-label { overflow: hidden; text-overflow: ellipsis; }
        .sidebar-footer { border-top: 1px solid rgba(255,255,255,.09); padding: .8rem .7rem; }
        .sidebar-user { display: flex; gap: .7rem; align-items: center; padding: .55rem .6rem .75rem; }
        .sidebar-avatar {
            flex: 0 0 36px; width: 36px; height: 36px; display: grid; place-items: center;
            border-radius: 50%; background: #344054; color: var(--yellow); font-weight: 900;
        }
        .sidebar-user-meta { min-width: 0; line-height: 1.25; }
        .sidebar-user-name { font-size: .78rem; font-weight: 900; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 180px; }
        .sidebar-user-role { color:#98a2b3; font-size:.67rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px; }
        .sidebar-logout { width: 100%; border: 0; background: transparent; text-align: left; }
        .app-content { min-height: 100vh; margin-left: var(--sidebar-width); transition: margin-left .22s ease; }
        .mobile-topbar { display: none; }
        .sidebar-backdrop { display: none; }

        body.sidebar-collapsed .app-sidebar { width: var(--sidebar-collapsed-width); }
        body.sidebar-collapsed .app-content { margin-left: var(--sidebar-collapsed-width); }
        body.sidebar-collapsed .brand-text,
        body.sidebar-collapsed .sidebar-section-title,
        body.sidebar-collapsed .nav-label,
        body.sidebar-collapsed .sidebar-user-meta { display: none; }
        body.sidebar-collapsed .sidebar-brand { padding-inline: .55rem; justify-content: center; flex-wrap: wrap; gap: .45rem; }
        body.sidebar-collapsed .brand-mark { flex-basis: 42px; width: 42px; height: 42px; }
        body.sidebar-collapsed .sidebar-toggle { flex-basis: 32px; width: 32px; height: 32px; }
        body.sidebar-collapsed .sidebar-link { justify-content: center; padding-inline: .5rem; }
        body.sidebar-collapsed .sidebar-user { justify-content: center; padding-inline: .2rem; }
        body.sidebar-collapsed .sidebar-logout { text-align: center; }

        @media (max-width: 991.98px) {
            .app-sidebar { width: 282px; transform: translateX(-100%); }
            .app-content, body.sidebar-collapsed .app-content { margin-left: 0; padding-top: 58px; }
            body.sidebar-collapsed .app-sidebar { width: 282px; }
            body.sidebar-collapsed .brand-text,
            body.sidebar-collapsed .sidebar-section-title,
            body.sidebar-collapsed .nav-label,
            body.sidebar-collapsed .sidebar-user-meta { display: block; }
            body.sidebar-collapsed .sidebar-brand { padding: .9rem .85rem; justify-content: flex-start; flex-wrap: nowrap; gap: .75rem; }
            body.sidebar-collapsed .sidebar-link { justify-content: flex-start; padding: .65rem .72rem; }
            body.sidebar-collapsed .sidebar-user { justify-content: flex-start; padding: .55rem .6rem .75rem; }
            body.sidebar-mobile-open .app-sidebar { transform: translateX(0); }
            .mobile-topbar {
                position: fixed; inset: 0 0 auto 0; height: 58px; z-index: 1030;
                display: flex; align-items: center; gap: .7rem; padding: .55rem .8rem;
                background: #101828; color: #fff; border-bottom: 3px solid var(--yellow);
            }
            .mobile-topbar .brand-mark { width: 36px; height: 36px; flex-basis: 36px; border-radius: 8px; font-size: .8rem; }
            .mobile-topbar-title { font-size: .85rem; font-weight: 900; flex: 1; }
            .sidebar-backdrop { position: fixed; inset: 0; z-index: 1035; background: rgba(16,24,40,.55); }
            body.sidebar-mobile-open .sidebar-backdrop { display: block; }
        }
        @media (max-width: 767px) {
            .page-shell { padding-left: .6rem !important; padding-right: .6rem !important; }
            .report-table { font-size: 11px; }
            .filter-panel { border-radius: 8px; }
        }
    </style>
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-responsive.css') }}?v=20260909-v2">
    <link rel="stylesheet" href="{{ asset('css/mobile-dashboard-fix.css') }}?v=20260909-v3">
</head>
<body>
@php
    $dashboardMenuAccess = $dashboardMenuAccess ?? [];
    $dashboardRole = session(config('dashboard_security.session_user_role_key'));
    $dashboardRoleLabel = $dashboardRoleLabel ?? config('dashboard_security.roles.'.$dashboardRole, strtoupper((string) $dashboardRole));
    $dashboardName = session(config('dashboard_security.session_user_name_key'), session(config('dashboard_auth.username_session_key')));
@endphp

<aside class="app-sidebar" id="appSidebar" aria-label="Navigasi utama">
    <div class="sidebar-brand">
        <div class="brand-mark">SR</div>
        <div class="brand-text">
            <div class="brand-title">Sales Dashboard</div>
            <div class="brand-subtitle">AS_INGCO &amp; AS_SMI</div>
        </div>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Tampilkan atau sembunyikan sidebar" title="Show / Hide menu">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>
    <div class="sidebar-scroll">
        <div class="sidebar-section-title">LAPORAN</div>
        <nav class="sidebar-nav">
            @if ($dashboardMenuAccess['sales_dashboard'] ?? false)
                <a href="{{ route('sales-dashboard.index') }}" title="Dashboard Penjualan" class="sidebar-link {{ request()->routeIs('sales-dashboard.*') ? 'active' : '' }}"><i class="bi bi-graph-up-arrow sidebar-icon"></i><span class="nav-label">Dashboard Penjualan</span></a>
            @endif
            @if ($dashboardMenuAccess['sales_review_3_months'] ?? false)
                <a href="{{ route('sales-review-3-months.index') }}" title="Review Penjualan 3 Bulan" class="sidebar-link {{ request()->routeIs('sales-review-3-months.*') ? 'active' : '' }}"><i class="bi bi-calendar3-range sidebar-icon"></i><span class="nav-label">Review Penjualan 3 Bulan</span></a>
            @endif
            @if ($dashboardMenuAccess['customer_analysis'] ?? false)
                <a href="{{ route('customer-analysis.index') }}" title="Analisa Customer" class="sidebar-link {{ request()->routeIs('customer-analysis.*') ? 'active' : '' }}"><i class="bi bi-person-lines-fill sidebar-icon"></i><span class="nav-label">Analisa Customer</span></a>
            @endif
            @if ($dashboardMenuAccess['sales_kpi'] ?? false)
                <a href="{{ route('sales-kpi.index') }}" title="Dashboard KPI" class="sidebar-link {{ request()->routeIs('sales-kpi.index') ? 'active' : '' }}"><i class="bi bi-speedometer2 sidebar-icon"></i><span class="nav-label">Dashboard KPI</span></a>
            @endif
            @if ($dashboardMenuAccess['sales_insights'] ?? false)
                <a href="{{ route('sales-insights.index') }}" title="Sales Insights & R&amp;D" class="sidebar-link {{ request()->routeIs('sales-insights.*') ? 'active' : '' }}"><i class="bi bi-lightbulb sidebar-icon"></i><span class="nav-label">Sales Insights &amp; R&amp;D</span></a>
            @endif
            @if ($dashboardMenuAccess['business_analytics'] ?? false)
                <a href="{{ route('business-analytics.index') }}" title="Business Analytics" class="sidebar-link {{ request()->routeIs('business-analytics.index') ? 'active' : '' }}"><i class="bi bi-bar-chart-line sidebar-icon"></i><span class="nav-label">Business Analytics</span></a>
                <a href="{{ route('business-analytics.product-advisor.index') }}" title="Product &amp; Sales Strategy Advisor" class="sidebar-link {{ request()->routeIs('business-analytics.product-advisor.*') ? 'active' : '' }}"><i class="bi bi-bullseye sidebar-icon"></i><span class="nav-label">Product Strategy Advisor</span></a>
            @endif
            @if ($dashboardMenuAccess['stock_aging'] ?? false)
                <a href="{{ route('stock-aging.index') }}" title="Sisa Stok &amp; Stock Aging" class="sidebar-link {{ request()->routeIs('stock-aging.index') || request()->routeIs('stock-aging.details*') ? 'active' : '' }}"><i class="bi bi-box-seam sidebar-icon"></i><span class="nav-label">Sisa Stok &amp; Stock Aging</span></a>
                <a href="{{ route('stock-aging.strategy-review') }}" title="Stock Strategy Review" class="sidebar-link {{ request()->routeIs('stock-aging.strategy-review') ? 'active' : '' }}"><i class="bi bi-sort-down-alt sidebar-icon"></i><span class="nav-label">Stock Strategy Review</span></a>
            @endif
            {{-- Pencarian Sparepart: menu laporan, bukan master data --}}
            @if ($dashboardMenuAccess['sparepart_search'] ?? false)
                <a href="{{ route('spareparts.search') }}" title="Pencarian Sparepart" class="sidebar-link {{ request()->routeIs('spareparts.*') ? 'active' : '' }}"><i class="bi bi-tools sidebar-icon"></i><span class="nav-label">Pencarian Sparepart</span></a>
            @endif
        </nav>

        @if (($dashboardMenuAccess['product_focus'] ?? false) || ($dashboardMenuAccess['similar_items'] ?? false))
            <div class="sidebar-section-title mt-2">MASTER DATA</div>
            <nav class="sidebar-nav">
                @if ($dashboardMenuAccess['product_focus'] ?? false)
                    <a href="{{ route('product-strategy.product-focus.index') }}" title="Product Focus" class="sidebar-link {{ request()->routeIs('product-strategy.product-focus.*') ? 'active' : '' }}"><i class="bi bi-stars sidebar-icon"></i><span class="nav-label">Product Focus</span></a>
                @endif
                @if ($dashboardMenuAccess['similar_items'] ?? false)
                    <a href="{{ route('product-strategy.similar-items.index') }}" title="Similar Items" class="sidebar-link {{ request()->routeIs('product-strategy.similar-items.*') ? 'active' : '' }}"><i class="bi bi-diagram-3 sidebar-icon"></i><span class="nav-label">Similar Items</span></a>
                @endif
            </nav>
        @endif
        @if ($dashboardMenuAccess['kpi_targets'] ?? false)
            <div class="sidebar-section-title mt-2">PENGATURAN</div>
            <nav class="sidebar-nav"><a href="{{ route('sales-kpi.targets.index') }}" title="Pengaturan Target KPI" class="sidebar-link {{ request()->routeIs('sales-kpi.targets.*') ? 'active' : '' }}"><i class="bi bi-sliders sidebar-icon"></i><span class="nav-label">Pengaturan Target KPI</span></a></nav>
        @endif
        @if (($dashboardMenuAccess['security_users'] ?? false) || ($dashboardMenuAccess['security_roles'] ?? false) || ($dashboardMenuAccess['security_devices'] ?? false) || ($dashboardMenuAccess['security_audits'] ?? false))
            <div class="sidebar-section-title mt-2">KEAMANAN</div>
            <nav class="sidebar-nav">
                @if ($dashboardMenuAccess['security_users'] ?? false)
                    <a href="{{ route('security.users.index') }}" title="Pengguna" class="sidebar-link {{ request()->routeIs('security.users.*') ? 'active' : '' }}"><i class="bi bi-people sidebar-icon"></i><span class="nav-label">Pengguna</span></a>
                @endif
                @if ($dashboardMenuAccess['security_roles'] ?? false)
                    <a href="{{ route('security.roles.index') }}" title="Role &amp; Permission" class="sidebar-link {{ request()->routeIs('security.roles.*') ? 'active' : '' }}"><i class="bi bi-shield-lock sidebar-icon"></i><span class="nav-label">Role &amp; Permission</span></a>
                @endif
                @if ($dashboardMenuAccess['security_devices'] ?? false)
                    <a href="{{ route('security.devices.index') }}" title="Riwayat Perangkat" class="sidebar-link {{ request()->routeIs('security.devices.*') ? 'active' : '' }}"><i class="bi bi-laptop sidebar-icon"></i><span class="nav-label">Riwayat Perangkat</span></a>
                @endif
                @if ($dashboardMenuAccess['security_audits'] ?? false)
                    <a href="{{ route('security.audits.index') }}" title="Audit Aktivitas" class="sidebar-link {{ request()->routeIs('security.audits.*') ? 'active' : '' }}"><i class="bi bi-journal-text sidebar-icon"></i><span class="nav-label">Audit Aktivitas</span></a>
                @endif
            </nav>
        @endif
    </div>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">{{ strtoupper(substr((string) $dashboardName, 0, 1)) }}</div>
            <div class="sidebar-user-meta"><div class="sidebar-user-name">{{ $dashboardName }}</div><div class="sidebar-user-role">{{ $dashboardRoleLabel }}</div></div>
        </div>
        <nav class="sidebar-nav">
            <a href="{{ route('account.password.edit') }}" title="Ganti Password" class="sidebar-link {{ request()->routeIs('account.password.*') ? 'active' : '' }}"><i class="bi bi-key sidebar-icon"></i><span class="nav-label">Ganti Password</span></a>
            <form method="POST" action="{{ route('dashboard.logout') }}" class="m-0">@csrf
                <button type="submit" title="Keluar" class="sidebar-link sidebar-logout"><i class="bi bi-box-arrow-left sidebar-icon"></i><span class="nav-label">Keluar</span></button>
            </form>
        </nav>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="mobile-topbar">
    <button type="button" class="sidebar-toggle" id="mobileSidebarToggle" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <div class="brand-mark">SR</div>
    <div class="mobile-topbar-title">Sales Report Dashboard</div>
</div>
<div class="app-content">
    <main class="page-shell px-3 px-lg-4 py-3 py-lg-4">@yield('content')</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const body = document.body;
    const desktopToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    const storageKey = 'sales-dashboard-sidebar-collapsed';
    try {
        if (window.localStorage.getItem(storageKey) === '1' && window.innerWidth >= 992) body.classList.add('sidebar-collapsed');
    } catch (e) {}
    desktopToggle?.addEventListener('click', function () {
        if (window.innerWidth < 992) { body.classList.toggle('sidebar-mobile-open'); return; }
        body.classList.toggle('sidebar-collapsed');
        try { window.localStorage.setItem(storageKey, body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch (e) {}
    });
    mobileToggle?.addEventListener('click', function () { body.classList.add('sidebar-mobile-open'); });
    backdrop?.addEventListener('click', function () { body.classList.remove('sidebar-mobile-open'); });
    document.querySelectorAll('.app-sidebar a').forEach(function (link) {
        link.addEventListener('click', function () { if (window.innerWidth < 992) body.classList.remove('sidebar-mobile-open'); });
    });
    window.addEventListener('resize', function () { if (window.innerWidth >= 992) body.classList.remove('sidebar-mobile-open'); });
})();
</script>
@stack('scripts')
</body>
</html>
