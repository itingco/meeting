# INGCO Meeting AI - Patch 2 (15 Okt 2026)

## Perubahan

1. **User & akses**: admin dapat membuat user, menonaktifkan user, menetapkan akses **History** dan **Pengaturan** secara terpisah. Semua akun bisa merekam. Tanpa akses History, hanya meeting miliknya yang bisa dibuka. Hanya pembuat meeting atau administrator bisa menjalankan tindakan manual. Akun bisa mengubah password sendiri.
2. **Tombol mikrofon**: gambar mic adalah tombol Mulai/Berhenti. Tombol besar di bawah dihapus.
3. **Satu tombol manual** pada halaman rincian: **Sinkronkan Drive & Generate Notulen**. Tombol tersebut menjadwalkan backup yang belum berhasil, lalu menjadwalkan pemrosesan notulen bila AI sudah siap. Jika konfigurasi AI belum tersedia, backup Drive tetap dijadwalkan dan statusnya ditampilkan. Tidak menghapus audio.
4. **Pelacakan tahapan**: antrean AI, antrean Drive, status chunk, waktu update tahap, pesan error. Tahap: queued → combining → transcribing → summarizing → document → email (jika aktif) → completed. Status diperbarui tiap 5 detik; rincian error ada di halaman.
5. **Auto-processing worker Windows**: patch menambahkan Scheduled Task `INGCO Meeting AI - Processing Worker` terpisah dari Drive Worker yang sudah terpasang. Worker otomatis di-restart saat berhenti selama akun Windows login.

## Cara pasang (source code saja, tidak menimpa .env/database)

1. **Backup folder project dan database** `C:\xampp82\htdocs\meeting\database\database.sqlite` sebelum memasang.
2. **Hentikan sementara proses perekaman yang aktif**. Tutup worker processing manual (`Ctrl+C`) jika ada untuk menghindari worker duplikat. Drive worker otomatis boleh tetap menyala.
3. Ekstrak ZIP dan **salin isi folder ke** `C:\xampp82\htdocs\meeting` menggunakan struktur folder yang sama, **overwrite hanya file yang ada di dalam ZIP**. Jangan membuat folder bersarang `meeting\meeting`.
4. Jalankan:

   ```powershell
   cd C:\xampp82\htdocs\meeting
   php artisan migrate
   php artisan optimize:clear
   php artisan view:clear
   php artisan meeting:doctor
   ```

5. **Cek administrator**: migrasi menetapkan user ber-ID paling awal sebagai admin saat upgrade. Bila user login Anda tidak mendapat menu "User", jalankan perintah `php artisan meeting:admin --email=EMAIL_ADMIN_ANDA` lalu masukkan password baru minimal 12 karakter. Perintah tersebut akan memperbarui admin terpilih dan password-nya. Login ulang.
6. **Pasang worker otomatis pemrosesan** (PowerShell Administrator):

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\deploy\windows\install-processing-worker.ps1
   ```

   Harapan: status `Running`. Drive Worker yang sudah dipasang tidak perlu diinstal ulang.
7. Pastikan `QUEUE_CONNECTION=database`, `GOOGLE_DRIVE_ENABLED=true`, `FFMPEG_BINARY=ffmpeg` (FFmpeg sudah ada di PATH). Untuk notulen sungguhan, wajib `OPENAI_API_KEY` dan `MEETING_DEMO_AI=false`. Jika belum diisi, halaman hasil akan menunjukkan error transkripsi/konfigurasi, sehingga proses tidak tampak "diam". Untuk pengujian flow saja, boleh `MEETING_DEMO_AI=true`, **hasilnya bukan isi meeting asli**.
8. Rekam 30-60 detik: klik ikon mic untuk mulai, klik lagi untuk berhenti. Setelah beberapa detik diarahkan ke detail meeting yang memiliki progress. Untuk meeting lama yang masih `QUEUED`, klik **Sinkronkan Drive & Generate Notulen**.

## Diagnostik

```powershell
Get-ScheduledTask -TaskName 'INGCO Meeting AI - Drive Worker', 'INGCO Meeting AI - Processing Worker' | Select TaskName,State
php artisan queue:monitor database:processing --max=100
php artisan queue:monitor database:drive --max=100
php artisan queue:failed
Get-Content .\storage\logs\processing-worker.log -Tail 30
Get-Content .\storage\logs\drive-worker.log -Tail 30
Get-Content .\storage\logs\laravel.log -Tail 40
```

- `QUEUED` dengan antrean >0: menunggu atau dijalankan worker processing; jika lama, cek Scheduled Task dan log.
- `PROCESSING`: tahap terkini (gabung/transkripsi/notulen/Word/email) tampil di halaman.
- `FAILED`: halaman menampilkan tahap + pesan error; perbaiki konfigurasi lalu tekan Generate Notulen.
- Drive `x/y`: jumlah potongan yang sudah terupload; file final/Word ditampilkan terpisah sebagai status Drive final.
- Setelah berhasil, otomatisasi tetap mensyaratkan **komputer server menyala, akun Windows tempat task didaftarkan sedang login, dan internet aktif**. Untuk hosting produksi gunakan Supervisor/systemd atau Windows Service yang dikelola IT; Scheduled Task `AtLogOn` belum memastikan worker aktif tanpa login.
- **Browser mobile** butuh HTTPS, tidak dijamin merekam saat layar terkunci atau app background. Minta izin peserta sebelum merekam; batasi folder Drive dan akun sistem.

### Test opsional

```powershell
php artisan test --filter=AccessAndPipelineTest
```

### Catatan keamanan

Tidak ada kredensial SMTP/API key/Google dalam ZIP ini. File `.env` dan `database.sqlite` tidak disertakan. Bila password email pernah dipublikasikan, ganti password tersebut. Pengiriman email **tidak akan berjalan** sampai SMTP dikonfigurasi dan `MEETING_MAIL_ENABLED=true`.
