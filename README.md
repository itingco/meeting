# INGCO Meeting AI

Aplikasi Laravel 10 untuk merekam rapat melalui browser mobile, menyimpan potongan audio secara berkala (lokal perangkat + server), melakukan backup Google Drive melalui queue, mentranskrip dengan AI, membuat notulen Word, dan mengirim email otomatis.

**Stack:** PHP >=8.1, Laravel 10, SQLite untuk demo / PostgreSQL untuk produksi, Google Drive OAuth2, OpenAI API, PhpWord, FFmpeg, SMTP Niagahoster. Antarmuka Blade/CSS/JS tanpa Vite atau npm.

## Penting sebelum pengujian

- Source ZIP **belum menyertakan folder `vendor/`** (dependensi Laravel diinstal lewat Composer) dan belum pernah dijalankan end-to-end dengan API sungguhan di lingkungan pembuatan ini. Pemeriksaan sintaks PHP dan JS sudah dilakukan.
- File `.env` dibuat sendiri dan bersifat rahasia. Password email yang telah dibagikan dalam percakapan sebaiknya diganti/dirotasi; jangan commit password, API key, atau token OAuth ke Git.
- Penggunaan mikrofon memerlukan **HTTPS atau localhost**. Akses HP lewat `http://192.168.x.x:8000` tidak dianggap secure context di banyak browser, sehingga mic bisa ditolak. Gunakan domain HTTPS / tunneling HTTPS.
- Safari/Chrome mobile **tidak menjamin perekaman di background atau ketika layar terkunci**. Pertahankan halaman aktif dan layar menyala; web dapat menyelamatkan audio yang sudah dipancarkan MediaRecorder tetapi tidak dapat menyelamatkan kata-kata yang tidak terekam.
- Minta persetujuan peserta sebelum merekam; transkrip berisi data rapat yang dikirim ke penyedia AI dan ke Google Drive jika opsi diaktifkan.

## A. Instalasi cepat di Windows (XAMPP PHP 8.2)

1. Ekstrak ZIP ke `C:\xampp82\htdocs\meeting-ai-laravel` (atau path PHP XAMPP yang sesuai).
2. Pastikan `php -v` dan `composer --version` tersedia di Terminal/PowerShell. Aktifkan ekstensi PHP: `fileinfo`, `mbstring`, `openssl`, `zip`, `pdo_sqlite` atau `pdo_pgsql`. Pastikan PHP yang dipakai Composer adalah PHP XAMPP yang benar.
3. Instal FFmpeg dan tambahkan folder `bin` ke PATH, atau atur `FFMPEG_BINARY="C:\\ffmpeg\\bin\\ffmpeg.exe"` pada `.env`.
4. Jalankan dari terminal folder project:

   ```powershell
   composer install
   copy .env.example .env
   New-Item database\database.sqlite -ItemType File -Force
   php artisan key:generate
   php artisan migrate --seed
   php artisan meeting:admin --email=admin@ingco.co.id --name=Admin
   php artisan meeting:doctor
   ```

   Saat `meeting:admin` muncul prompt, masukkan password baru minimal 12 karakter (bukan password email SMTP).

5. Untuk mencoba UI/notulen **tanpa API eksternal**, di `.env` set:

   ```dotenv
   MEETING_DEMO_AI=true
   GOOGLE_DRIVE_ENABLED=false
   MEETING_MAIL_ENABLED=false
   ```

   Ingat: *mode demo tidak mentranskripsi rekaman*; Word contoh akan diberi label DEMO. Rekaman tetap ditangkap dan diproses FFmpeg.

6. Jalankan **tiga terminal** (biarkan tetap terbuka selama pengujian):

   Terminal 1 – web:
   ```powershell
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   Terminal 2 – pengolah rapat/AI/Word/email:
   ```powershell
   php artisan queue:work database --queue=processing --sleep=1 --tries=1 --timeout=3400
   ```

   Terminal 3 – backup Google Drive:
   ```powershell
   php artisan queue:work database --queue=drive --sleep=1 --tries=5 --timeout=300
   ```

7. Buka <http://127.0.0.1:8000>, login, tekan **Mulai Rekaman**, izinkan microphone, berbicara 15–30 detik, kemudian **Selesai & Buat Notulen**. Periksa detail meeting dan unduh Word.

Jika ingin akses HP untuk uji real, gunakan deployment/domain **HTTPS** ke server jaringan yang terjangkau HP. Browser HP tidak boleh diakses via HTTP-IP biasa untuk menggunakan mic.

## B. Aktifkan transkripsi AI sungguhan

1. Dapatkan **OpenAI API key** dari akun API yang punya kredit/izin penggunaan. Langganan ChatGPT dan penggunaan API ditagih/diatur terpisah.
2. Isi `.env` (jangan bagikan key):

   ```dotenv
   MEETING_DEMO_AI=false
   OPENAI_API_KEY=isi_key_anda
   OPENAI_TRANSCRIBE_MODEL=gpt-4o-mini-transcribe
   OPENAI_SUMMARY_MODEL=gpt-4.1-mini
   ```

3. Jalankan `php artisan config:clear` dan restart kedua worker queue.
4. Audio akhir diubah menjadi M4A mono 48 kbps, dipotong per 10 menit sehingga tiap bagian dapat diproses API transkripsi, lalu diringkas. AI menandai data yang tidak disebutkan dan **bisa tetap keliru**; tinjau hasilnya sebelum dipakai sebagai catatan resmi.

## C. Koneksi Google Drive

Folder induk yang disiapkan:

`https://drive.google.com/drive/folders/1-J92rqZP8K-VmY-XHVL73YhAJYC7CZG0`

Penyimpanan:

```text
<folder induk Google Drive>/
  2026-10-14/
    0930_Weekly_Meeting_8karakter/
      chunk_000000.webm
      chunk_000001.webm
      ...
      rekaman-final.m4a
      notulen.docx
```

Aplikasi menyimpan potongan audio di server saat diterima. Dengan Drive aktif, potongan langsung masuk antrean upload cloud tanpa menunggu meeting selesai. Folder tanggal diambil dari waktu mulai meeting dalam zona Asia/Jakarta.

Cara koneksi OAuth (akun Google yang mempunyai **izin Editor** pada folder induk):

1. Di [Google Cloud Console](https://console.cloud.google.com/), buat project, **enable Google Drive API**, konfigurasi OAuth Consent Screen (Internal bila Workspace organisasi mengizinkan; bila External lakukan pengaturan pengguna uji/verified sesuai kebijakan Google), lalu buat OAuth Client ID jenis **Web application**.
2. Tambah *Authorized redirect URI* persis sesuai URL aplikasi, misalnya:
   `https://meeting.ciptagroup.id/settings/google/callback`
3. Ubah `.env`:

   ```dotenv
   GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxx
   GOOGLE_REDIRECT_URI=https://meeting.ciptagroup.id/settings/google/callback
   GOOGLE_DRIVE_PARENT_ID=1-J92rqZP8K-VmY-XHVL73YhAJYC7CZG0
   GOOGLE_DRIVE_ENABLED=false
   ```

4. `php artisan config:clear` → buka aplikasi → **Pengaturan → Hubungkan Google Drive** → beri izin ke akun yang bisa menulis pada folder induk. OAuth scope yang dipakai `https://www.googleapis.com/auth/drive`, yang bersifat luas/sensitif dan mungkin memerlukan verifikasi menurut kebijakan Google.
5. Setelah status **Terhubung**, set `GOOGLE_DRIVE_ENABLED=true`, jalankan `php artisan config:clear`, restart worker queue, lalu lakukan rekaman baru.
6. **Jika backup gagal**, rekaman tetap disimpan di `storage/app/meetings/<UUID>/chunks`, dan job akan retry sesuai konfigurasi. Pantau queue failed jobs dan log.

> Pilihan OAuth user digunakan karena service account reguler bisa terkendala kepemilikan/kuota My Drive. Folder target harus bisa ditulis oleh akun Google yang mengotorisasi aplikasi.

## D. Aktifkan SMTP mail `ai@ingco.co.id`

Konfigurasi sesuai gambar yang diberikan:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=srv179.niagahoster.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=ai@ingco.co.id
MAIL_PASSWORD="ISI_PASSWORD_EMAIL_DI_SINI"
MAIL_FROM_ADDRESS=ai@ingco.co.id
MAIL_FROM_NAME="INGCO Meeting AI"
MEETING_MAIL_ENABLED=true
MEETING_INITIAL_RECIPIENT=antoni@ingco.co.id
MEETING_RECIPIENT_ROUTING_ENABLED=false
```

**Password yang ditunjukkan pengguna tidak disalin ke dalam project.** Masukkan password terbaru langsung ke `.env` di komputer lokal dan jangan disebarkan bersama ZIP. Bila password mengandung karakter seperti `#`, `;`, `?`, bungkus dalam tanda kutip ganda. Setelah ubah `.env`, jalankan:

```powershell
php artisan config:clear
php artisan queue:restart
```

Saat proses notulen selesai, sistem langsung mengirim Word sebagai attachment ke **antoni@ingco.co.id** jika mail diaktifkan. `MAIL_ENCRYPTION=ssl` memakai SMTP port 465; IMAP 993 adalah untuk membaca email dan **tidak diperlukan** aplikasi ini.

## E. Routing email per departemen (DISIAPKAN, NONAKTIF)

Halaman **Pengaturan → Email departemen** memuat IT, OPS, Finance, IC, RND, CMC, HC, Legal, CEO, GM. Email dapat diisi sekarang tetapi **tidak menjadi penerima** sampai `MEETING_RECIPIENT_ROUTING_ENABLED=true`. Saat itu:

- `antoni@ingco.co.id` tetap menjadi penerima dasar (selama env tidak diubah).
- CEO dan GM akan selalu ditambahkan; alamat email CEO dan GM **wajib diisi** sebelum mengaktifkan routing.
- Departemen lain hanya dikirim bila checkbox aktif dan email terisi.

Tidak ada pengiriman massal departemen dalam konfigurasi awal.

## F. Struktur kode utama

```text
app/Http/Controllers/MeetingController.php   API create / chunk / stop / detail / download
app/Http/Controllers/SettingController.php   penerima departemen + OAuth Google
app/Jobs/UploadChunkToDrive.php             backup potongan audio (queue drive)
app/Jobs/UploadArtifactsToDrive.php         backup audio final dan Word
app/Jobs/ProcessMeeting.php                FFmpeg → AI → Word → Email
app/Services/MeetingAiService.php          transkripsi OpenAI & notulen JSON
app/Services/WordMinutesService.php        Word PhpWord
app/Services/GoogleDriveService.php        OAuth / folder per tanggal / upload
app/Mail/MeetingMinutesMail.php            email dengan attachment
public/js/recorder.js                      MediaRecorder + IndexedDB recovery
public/css/app.css                         mobile-first
resources/views/                           Blade
```

## G. Masalah umum

| Gejala | Penyebab / perbaikan |
|---|---|
| Mikrofon tidak tersedia di ponsel | URL `http://IP-LAN` (bukan secure context). Pakai HTTPS atau localhost. |
| Setelah klik Stop tidak menjadi Word | Worker `processing` belum berjalan. Jalankan `queue:work`, periksa `php artisan queue:failed` dan `storage/logs/laravel.log`. |
| `FFmpeg gagal` | Install FFmpeg, atur `FFMPEG_BINARY`, tes `ffmpeg -version`. |
| `could not find driver` | `pdo_sqlite`/`pdo_pgsql` belum aktif pada PHP yang digunakan. |
| OAuth redirect_mismatch | Google Console authorized redirect URI tidak sama persis dengan `.env`. |
| Drive `insufficientPermissions` atau 404 | Akun Google belum menjadi Editor pada folder induk / salah scope. |
| Google API refresh token tidak ada | Putuskan lalu hubungkan ulang akun, pastikan offline access. |
| Audio tidak terupload setelah jaringan putus | Buka kembali dashboard **di browser & perangkat yang sama**, pilih pulihkan; jangan hapus data situs/IndexedDB. |
| Email tidak terkirim | `MEETING_MAIL_ENABLED=true`, SMTP host/port/password benar, firewall mengizinkan 465, cek log. |
| Notulen tidak akurat | Kualitas audio/bising/tumpang tindih suara, model AI dapat keliru, lakukan verifikasi manual. |
| `APP_KEY missing` | Jalankan `php artisan key:generate`. |

Untuk mengulang pekerjaan yang gagal: buka detail meeting, klik **Coba Proses Ulang** setelah penyebab diperbaiki. File rekaman dan transkrip yang sudah ada tetap tersimpan.

## H. Catatan produksi

- Set `APP_ENV=production`, `APP_DEBUG=false`, ganti APP_KEY dengan key aman, gunakan HTTPS, database PostgreSQL 15 dan backup database/audio.
- Arahkan document root web server ke `public/`, **jangan** ke root project (menghindari kebocoran `.env`).
- Jalankan **2 worker queue sebagai service persisten** (Supervisor/systemd di Linux). Contoh ada di `deploy/supervisor-example.conf`. Pada worker processing, pastikan timeout queue `retry_after` lebih besar daripada timeout job agar tidak duplikat proses.
- Konfigurasi pembatasan akses admin internal, kebijakan retensi rekaman, hak akses Drive, audit log dan consent peserta sesuai kebijakan perusahaan.
- Upload audio melalui POST multipart maksimal 8 MB/potongan. MediaRecorder mengirim sekitar tiap 15 detik; ritme sesungguhnya tergantung browser.
- Rekaman yang tertunda di perangkat bergantung pada kuota IndexedDB browser, *tidak menjamin* semua data selamat dari uninstall, clear browsing data, kehabisan storage, atau force close sebelum chunk dibuat.
- Pastikan `post_max_size`, `upload_max_filesize` di PHP >= 16M; `memory_limit` disarankan >= 256M. Siapkan ruang disk dan retention policy.

## I. Testing checklist

- [ ] Login admin berhasil
- [ ] Browser HTTPS mengizinkan mic
- [ ] Setelah 15–30 detik, jumlah bagian tersimpan bertambah
- [ ] Putus jaringan sesaat, bagian pending terlihat; reconnect kemudian sinkron lagi
- [ ] Tekan Stop, meeting berubah `queued` → `processing` → `completed`
- [ ] Word bisa diunduh, file audio akhir bisa diunduh
- [ ] Dalam demo: isi bertanda DEMO; dalam real: transkrip mencerminkan percakapan
- [ ] Drive memiliki folder tanggal dan chunk
- [ ] Word terkirim ke antoni@ingco.co.id saat SMTP diaktifkan
- [ ] Penerima departemen tidak ikut terkirim saat routing nonaktif
#   m e e t i n g  
 