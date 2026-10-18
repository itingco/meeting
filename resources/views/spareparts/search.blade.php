@extends('layouts.app')

@section('title', 'Pencarian Sparepart & Similar Items')

@push('styles')
<style>
    .spare-hero{background:linear-gradient(115deg,#101828,#23415d);color:#fff;border-radius:16px;padding:1.45rem 1.5rem}
    .spare-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;box-shadow:0 5px 18px rgba(16,24,40,.045)}
    .spare-heading{font-size:1.05rem;color:#101828;font-weight:800}
    .spare-kicker{color:#ffc000;letter-spacing:.10em;font-size:.73rem;font-weight:900}
    .spare-stat{border-radius:12px;background:#f4f7fb;padding:1rem;border:1px solid #e2e8f0}
    .spare-stat .v{font-size:1.35rem;font-weight:900;overflow-wrap:anywhere}
    .spare-stat .k{font-size:.72rem;color:#667085;font-weight:800;text-transform:uppercase}
    .spare-table{min-width:640px;font-size:.84rem}
    .spare-table thead th{background:#f8fafc;color:#475467;border-bottom:1px solid #e2e8f0;white-space:nowrap}
    .spare-source{font-size:.72rem;font-weight:800;background:#e8eef5;color:#344054;border-radius:6px;padding:.18rem .4rem}
    .spare-empty{color:#667085;font-size:.86rem}
    .spare-item{border:1px solid #e4e7ec;border-radius:11px;overflow:hidden;margin-top:.6rem}
    .spare-item summary{cursor:pointer;display:flex;flex-wrap:wrap;gap:.7rem;align-items:center;padding:.8rem .9rem;list-style:none}
    .spare-item summary::-webkit-details-marker{display:none}
    .spare-item summary::after{content:'Lihat gudang';font-size:.74rem;color:#475467;margin-left:auto}
    .spare-item[open] summary{background:#f8fafc}
    .spare-item[open] summary::after{content:'Tutup rincian'}
    .spare-code{font-weight:900;color:#101828;overflow-wrap:anywhere}
    .spare-badge{font-size:.7rem;font-weight:800;border-radius:99px;padding:.28rem .65rem;background:#f2f4f7;color:#344054}
    .spare-badge.ok{color:#027a48;background:#d1fadf}
    .spare-badge.warn{color:#b54708;background:#fffaeb}
    .spare-priority{border-left:4px solid #f0b400;background:#fffaf0;border-radius:10px;padding:.85rem 1rem}
    @media(max-width:576px){.spare-hero{padding:1rem}.spare-card{padding:.85rem}.spare-stat .v{font-size:1.15rem}.spare-item summary::after{width:100%;margin-left:0}}
</style>
@endpush

@section('content')
@php
    $rupiah = static fn($number) => 'Rp '.number_format((float)$number, 0, ',', '.');
    $qty = static fn($number) => number_format((float)$number, 2, ',', '.');
@endphp

<div class="spare-hero mb-3 d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <div class="spare-kicker mb-1">INVENTORY • PART MATCHING</div>
        <h1 class="h4 fw-bold mb-1">Pencarian Sparepart</h1>
        <div class="text-white-50 small">Cari sparepart asli, stok sparepart pengganti, atau unit barang induk yang berpotensi menjadi sumber komponen.</div>
    </div>
    @if($dashboardMenuAccess['similar_items'] ?? false)
        <div><a class="btn btn-outline-light btn-sm" href="{{ route('product-strategy.similar-items.index') }}"><i class="bi bi-diagram-3 me-1"></i>Master Similar Items</a></div>
    @endif
</div>

<form method="GET" action="{{ route('spareparts.search') }}" class="spare-card mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-6">
            <label class="form-label fw-bold" for="spare-code">Kode Sparepart</label>
            <input class="form-control form-control-lg" id="spare-code" type="text" name="q" value="{{ $code }}" placeholder="Contoh: ABCD-SP-1" maxlength="100" pattern="[A-Za-z0-9._-]+" required autofocus autocomplete="off">
            <div class="form-text">Format barang induk: KODEBARANG-SP-NOMOR, misalnya ABCD-SP-1 → ABCD.</div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label fw-bold" for="spare-database">Sumber Data Stok</label>
            <select class="form-select form-select-lg" name="database" id="spare-database">
                <option value="all" @selected($database === 'all')>AS_INGCO + AS_SMI</option>
                <option value="ingco" @selected($database === 'ingco')>AS_INGCO</option>
                <option value="smi" @selected($database === 'smi')>AS_SMI</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 d-grid"><button type="submit" class="btn btn-report btn-lg"><i class="bi bi-search me-1"></i> Cari Sparepart</button></div>
    </div>
    @if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif
</form>

@if($result !== null)
    @if($result['errors'] !== [])
        <div class="alert alert-danger" role="alert">
            <strong>Hasil pencarian belum lengkap.</strong> Tidak aman menyimpulkan stok kosong saat ada koneksi gagal.
            <ul class="mb-0 mt-2">@foreach($result['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
            <div class="small mt-2">Detail teknis tersedia pada storage/logs/laravel.log.</div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="spare-stat h-100"><div class="k">Kode dicari</div><div class="v">{{ $result['code'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="spare-stat h-100"><div class="k">Stok asli</div><div class="v">{{ $qty($result['original']['total_qty']) }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="spare-stat h-100"><div class="k">Jumlah alternatif</div><div class="v">{{ count($result['similar']) }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="spare-stat h-100"><div class="k">Unit donor tersedia</div><div class="v">{{ count(array_filter($result['donors'], static fn($item) => $item['total_qty'] > 0)) }}</div></div></div>
    </div>

    @if($result['priority'] === 'original')
        <div class="spare-priority mb-3"><strong><i class="bi bi-check-circle-fill text-success me-1"></i> Sparepart asli tersedia.</strong> Prioritaskan pemenuhan dari stok asli.</div>
    @elseif($result['priority'] === 'similar')
        <div class="spare-priority mb-3"><strong><i class="bi bi-arrow-left-right me-1"></i> Sparepart asli kosong.</strong> Ada sparepart alternatif yang mempunyai stok. Pastikan kompatibilitasnya sebelum digunakan.</div>
    @elseif($result['priority'] === 'donor')
        <div class="spare-priority mb-3"><strong><i class="bi bi-tools me-1"></i> Semua kandidat sparepart kosong.</strong> Ditemukan unit barang induk yang mungkin dapat dijadikan sumber komponen, setelah persetujuan teknis.</div>
    @elseif($result['priority'] === 'none')
        <div class="alert alert-warning">Tidak ditemukan stok sparepart asli, alternatif, maupun unit barang induk pada database yang diperiksa.</div>
    @endif

    <section class="spare-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 class="spare-heading mb-0"><i class="bi bi-1-circle me-1"></i> Sparepart Asli</h2>
            <span class="spare-badge {{ $result['original']['total_qty'] > 0 ? 'ok' : 'warn' }}">{{ $result['priority'] === 'incomplete' ? ($result['original']['total_qty'] > 0 ? 'ADA STOK (SEBAGIAN)' : 'BELUM TERVERIFIKASI') : ($result['original']['total_qty'] > 0 ? 'TERSEDIA' : 'KOSONG') }}</span>
        </div>
        <div class="fw-bold">{{ $result['original']['item_code'] }} <span class="text-muted fw-normal">{{ $result['original']['item_name'] }}</span></div>
        @include('spareparts._stock_table', ['item' => $result['original'], 'incomplete' => $result['priority'] === 'incomplete'])
    </section>

    <section class="spare-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 class="spare-heading mb-0"><i class="bi bi-2-circle me-1"></i> Similar Spareparts</h2>
            <span class="spare-badge">{{ count($result['similar']) }} relasi ditemukan</span>
        </div>
        <p class="small text-muted mb-2">Mencari kedua arah dari master Similar Items. Relasi berantai (level 2+) wajib dikonfirmasi oleh bagian teknis.</p>
        @forelse($result['similar'] as $candidate)
            <details class="spare-item" @if($candidate['total_qty'] > 0) open @endif>
                <summary>
                    <span class="spare-code">{{ $candidate['item_code'] }}</span>
                    <span class="text-secondary small">{{ $candidate['item_name'] }}</span>
                    <span class="spare-badge">{{ $candidate['depth'] === 1 ? 'Relasi langsung' : 'Relasi berantai level '.$candidate['depth'] }}</span>
                    <span class="spare-badge {{ $candidate['total_qty'] > 0 ? 'ok' : 'warn' }}">{{ $result['priority'] === 'incomplete' ? ($candidate['total_qty'] > 0 ? 'Stok sebagian '.$qty($candidate['total_qty']) : 'Belum terverifikasi') : ($candidate['total_qty'] > 0 ? 'Stok '.$qty($candidate['total_qty']) : 'Kosong') }}</span>
                </summary>
                <div class="px-2 pb-2">@include('spareparts._stock_table', ['item' => $candidate, 'incomplete' => $result['priority'] === 'incomplete'])</div>
            </details>
        @empty
            <div class="spare-empty">Tidak ditemukan relasi untuk kode ini di master Similar Items.</div>
        @endforelse
    </section>

    @if($result['priority'] === 'donor' || $result['priority'] === 'none' || $result['donors'] !== [])
        <section class="spare-card mb-3">
            <h2 class="spare-heading"><i class="bi bi-3-circle me-1"></i> Alternatif Barang Induk / Unit Donor</h2>
            <div class="alert alert-warning small"><strong>Perlu persetujuan teknis:</strong> stok barang induk tidak otomatis berarti sparepart dapat dilepas, kompatibel, atau layak dijual. Verifikasi tipe produk, nomor revisi, kondisi, dan izin pembongkaran.</div>
            @forelse($result['donors'] as $donor)
                <div class="spare-item p-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                        <span class="spare-code">{{ $donor['item_code'] }}</span>
                        <span class="text-muted small">{{ $donor['item_name'] }}</span>
                        <span class="spare-badge {{ $donor['total_qty'] > 0 ? 'ok' : 'warn' }}">{{ $donor['total_qty'] > 0 ? 'Tersedia '.$qty($donor['total_qty']) : 'Kosong' }}</span>
                    </div>
                    <div class="small text-muted mb-2">Diturunkan dari: {{ implode(', ', $donor['for_spareparts']) }}</div>
                    @include('spareparts._stock_table', ['item' => $donor, 'incomplete' => $result['priority'] === 'incomplete'])
                </div>
            @empty
                <div class="spare-empty">Kode sparepart dan alternatif tidak memiliki format akhiran <code>-SP-NOMOR</code>, sehingga kode barang induk tidak dapat ditentukan otomatis.</div>
            @endforelse
        </section>
    @endif
    <div class="small text-muted mt-3">Diperiksa: {{ $result['searched_at'] }} WIB • Stok dari perhitungan ERP existing (USP_IC_Items_Calculate) dalam UOM terkecil; harga Price1 per price level retail. Angka stok dan harga mungkin memiliki UOM berbeda; konfirmasi ERP sebelum transaksi.</div>
@else
    <div class="spare-card text-center py-5">
        <i class="bi bi-search display-5 text-secondary"></i>
        <h2 class="h5 fw-bold mt-3">Siap mencari stok sparepart</h2>
        <p class="text-muted mb-0">Masukkan kode sparepart untuk melihat stok asli, item pengganti, dan opsi barang induk jika dibutuhkan.</p>
    </div>
@endif
@endsection
