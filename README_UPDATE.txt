UPDATE PENCARIAN SPAREPART - SALES DASHBOARD
============================================
Target: project yang SUDAH terpasang modul pencarian sparepart.
Laravel 12 / PHP >= 8.2. File ini tidak mengandung data stok atau kredensial.

PERUBAHAN:
1) SparepartInventoryRepository.php
   - Tidak lagi memanggil USP_Dashboard_SparepartStock (prosedur baru).
   - Memanggil prosedur ERP EXISTING dbo.USP_IC_Items_Calculate,
     menggunakan pola #SelectedItems / #SelectedWarehouses / #ItemBalancePerLocation
     yang sama dengan Stock Aging di database ERP.
   - #SelectedItems difilter langsung berdasarkan kode yang dicari,
     TANPA filter kategori BDG; sparepart dan barang induk ikut dicari.
   - Qty per gudang dalam UOM terkecil; harga Price1 dari IC_ItemPriceList
     pada SPAREPART_RETAIL_PRICE_LEVEL_ID (default 1, cek konfigurasi ERP).
   - Temporary tables hanya lokal untuk session SQL Server; tidak membuat USP baru.
2) SparepartSearchService.php
   - Mengganti teks error yang masih menyebut USP baru.
3) resources/views/layouts/app.blade.php
   - Tautan Pencarian Sparepart dipindahkan ke LAPORAN.
   - Tidak ada tautan sparepart dalam MASTER DATA.
4) config/dashboard_security.php
   - Catalog menu sparepart_search sekarang kategori Laporan.
   - Izin yang sudah ada tetap dipakai.
5) config/sparepart_search.php
   - Menghapus konfigurasi USP khusus yang tidak dipakai.
6) Blade sparepart: keterangan pembacaan stok dan status saat database gagal.

CARA PASANG (COPY PASTE MANUAL):
1. BACKUP file existing terutama:
   config/dashboard_security.php dan resources/views/layouts/app.blade.php
   karena kedua file ini mungkin berisi perubahan lokal Anda di luar GitHub.
2. Extract ZIP ini; di dalamnya langsung ada folder app/, config/, resources/.
3. COPY ketiga folder itu ke:
   C:\xampp82\htdocs\sales-report-web\
   pilih Replace/Overwrite pada file yang sama. JANGAN hapus folder project.
4. Jika layout/config Anda sudah dimodifikasi di luar repo,
   bandingkan dahulu file backup dengan patch agar perubahan lain tidak tertimpa.
5. Buka terminal pada root proyek, jalankan:
       php artisan optimize:clear
       php artisan route:list --path=spareparts
6. Login ulang, lalu buka http://127.0.0.1:8000/spareparts/search
   Menu pindah ke bagian LAPORAN.
7. Tes dengan kode sparepart yang benar-benar ada pada ERP, lakukan
   pengecekan lokasi dan qty terhadap sistem ERP.

TIDAK PERLU:
- Menjalankan CREATE/ALTER USP baru.
- Menjalankan migration baru.
- Mengulang import Similar Items.
- Menjalankan patch/installer PHP.

CATATAN:
- Stored procedure ERP bawaan `dbo.USP_IC_Items_Calculate` harus sudah ada,
  sama seperti yang dipanggil USP stock aging yang sekarang berfungsi.
- Akun DB mungkin membutuhkan EXECUTE langsung untuk prosedur existing itu.
  Jika Stock Aging berfungsi tetapi halaman baru error izin EXECUTE,
  minta DBA memberi izin EXECUTE pada prosedur tersebut (bukan membuat USP).
- Perhitungan stok bisa relatif berat; batasi jumlah kandidat Similar Items
  dengan SPAREPART_MAX_ALTERNATIVES atau SPAREPART_SIMILAR_DEPTH bila perlu.
- Harga `Price1` belum tentu menggunakan UOM terkecil; konfirmasi dengan ERP.
- Pengujian unit/logika dan sintaks PHP dapat dilakukan offline; pembacaan
  database AS_INGCO/AS_SMI belum dapat diverifikasi dari lingkungan ini.
