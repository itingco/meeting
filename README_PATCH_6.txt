INGCO Meeting AI - PATCH 6: PDF NOTULEN + AUDIO WAVEFORM (18-10-2026)

TARGET: C:\xampp82\htdocs\meeting
PATCH HANYA BERISI FILE YANG BERUBAH / DITAMBAH (tidak ada .env, database, atau password).
Sumber disusun dengan basis project awal + patch 2,3,4,5 yang Anda gunakan.

PERSIAPAN
1) Backup folder Laravel saat ini dan database/database.sqlite.
2) Hentikan Processing Worker (Task Scheduler) sementara sebelum copy, agar tidak ada job berjalan dengan kode lama.
3) Copy isi zip ke C:\xampp82\htdocs\meeting (merge folder; replace hanya file yang sama).
4) Instal paket PDF (perlu Composer, sekali saja):
      composer require "dompdf/dompdf:^3.1"
   Jika Composer menemukan advisori keamanan Laravel 10, jangan gunakan penonaktifan pemeriksaan keamanan pada server publik. Perbarui framework sebelum production.
5) Jalankan:
      php artisan optimize:clear
      php artisan meeting:doctor
      php artisan list | findstr meeting:pdf-backfill
6) Mulai kembali Processing Worker sesudah konfirmasi instalasi Dompdf berhasil. Restart worker yang menjalankan kode PHP lama.
7) HARD REFRESH browser Ctrl+F5 agar app.css dan recorder.js terbaru digunakan.

UJI PDF
- Meeting baru: akhiri rekaman; tunggu AI; file utama dibuat .pdf, tombol download PDF, email berlampiran PDF, backup final ke Drive nama notulen.pdf.
- Meeting lama yang mempunyai notes dan Word: tidak diproses ulang oleh Whisper/SumoPod, cukup:
      php artisan meeting:pdf-backfill
  (preview berapa data)
      php artisan meeting:pdf-backfill --apply
  (membuat PDF dari notes yang sudah ada tanpa mengirim ulang email)
  Jika perlu mengirim hasil PDF lama via email, lakukan manual; perintah ini sengaja tidak mengirim ulang otomatis untuk menghindari email duplikat.
- Bila sebelumnya Word ada di Drive, file Word lama tidak dihapus otomatis. Gunakan tombol Sinkronkan Drive untuk upload notulen.pdf.

UJI MIKROFON
- Masuk halaman Rekam, klik ikon mikrofon.
- Ketika ada suara yang cukup kuat, grafik bergerak dan label "Ada suara masuk".
- Jika hening, grafik mendekati rata dan label "Suara rendah / hening".
- Klik ikon mikrofon lagi untuk berhenti; visualizer berhenti, rekaman tetap memakai 15 detik per potongan dan mekanisme IndexedDB yang lama.
- Grafik hanya indikator LEVEL INPUT; bukan bukti ucapan akan dikenali sempurna. Pastikan izin mic sudah diberikan dan browser memakai HTTPS atau localhost.
- Saat halaman HP terkunci / background, browser bisa menghentikan mic. Tidak berubah oleh patch ini.

CATATAN:
- PDF memerlukan dompdf/dompdf (https://github.com/dompdf/dompdf).
- Storage/database dan pengaturan SMTP, SumoPod, Google OAuth tidak ditimpa.
- WordMinutesService.php lama tetap berada di project tetapi tidak digunakan untuk meeting baru. Aman dihapus setelah semua verifikasi selesai.
- Test di lokal dahulu. Laravel 10 perlu upgrade sebelum exposed production.
