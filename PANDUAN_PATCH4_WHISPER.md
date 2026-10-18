# INGCO Meeting AI - Patch 4 (Transkripsi Whisper Lokal)

Patch **hanya** mengganti/menambah file Laravel yang berkaitan dengan transkripsi. Isi ZIP dapat langsung di-copy ke `C:\xampp82\htdocs\meeting` dengan struktur direktori yang sama.

## File dalam patch

- `app/Services/MeetingAiService.php` (ubah): pilihan OpenAI/Whisper, eksekusi script Python dari Laravel, timeout dan pesan error.
- `config/meeting.php` (ubah): variabel konfigurasi Whisper; default nama model SumoPod diperbaiki ke `claude-haiku-4-5`.
- `app/Console/Commands/MeetingDoctor.php` (ubah): status Whisper + pengecekan paket Python.
- `app/Console/Commands/MeetingWhisperTest.php` (baru): tes transkripsi melalui Laravel, **tanpa mengambil pekerjaan dari antrean**.
- `scripts/whisper_transcribe.py` (baru): menjalankan `faster-whisper` secara lokal pada segmen rekaman.

## Tahap instalasi awal (belum jalankan Processing Worker)

1. **Backup** tiga file PHP yang ditimpa. Salin isi ZIP ke `C:\xampp82\htdocs\meeting`.
2. Pastikan Python virtualenv `.venv-whisper` sudah berisi `faster-whisper==1.2.1` dan `av==18.1.0` seperti yang sebelumnya berhasil diuji.
3. Tambahkan / ubah pengaturan `.env` (jangan salin API key ke pesan atau Git):

```dotenv
MEETING_TRANSCRIPTION_PROVIDER=whisper
WHISPER_PYTHON=C:/xampp82/htdocs/meeting/.venv-whisper/Scripts/python.exe
WHISPER_MODEL=small
WHISPER_DEVICE=cpu
WHISPER_COMPUTE_TYPE=int8
WHISPER_LANGUAGE=id
WHISPER_CPU_THREADS=6
WHISPER_BEAM_SIZE=3
WHISPER_TIMEOUT=1800
MEETING_SUMMARY_PROVIDER=sumopod
SUMOPOD_SUMMARY_MODEL=claude-haiku-4-5
MEETING_DEMO_AI=false
```

Nilai `OPENAI_API_KEY` tidak diperlukan untuk transkripsi ketika provider `whisper`, dan tidak perlu dihapus apabila disimpan untuk keperluan lain.

4. Jalankan:

```powershell
cd C:\xampp82\htdocs\meeting
php artisan config:clear
php artisan meeting:doctor
```

5. **Uji jalur Laravel ke Whisper** dengan audio pengujian sebelumnya:

```powershell
php artisan meeting:whisper-test "$env:TEMP\meeting-whisper-test.wav"
```

Target: `WHISPER LOKAL: BERHASIL` dan teks yang didengar (mis. "Terima kasih.").

## Cara kerja saat diaktifkan untuk meeting

`ProcessMeeting` yang sudah ada membagi audio gabungan menjadi potongan 10 menit, lalu memanggil `MeetingAiService::transcribe()` untuk tiap bagian. Dengan provider Whisper, Laravel memanggil Python pada audio segmen tersebut. Audio tidak diunggah ke OpenAI untuk transkripsi. Setelah transkripsi lengkap, penyusunan notulen masih memanggil SumoPod; Word dan Drive tetap menggunakan job sebelumnya.

**Jangan menjalankan `install-processing-worker.ps1` atau memproses antrean AI lama sampai tes `meeting:whisper-test` berhasil.** Antrean sebelumnya berisi beberapa job hasil klik manual; pastikan tidak ada duplikasi sebelum memproses semua.

## Batasan penting

- Whisper CPU memerlukan waktu. Untuk rapat **3 jam**, worker `processing` versi saat ini masih punya batas job `3300` detik (~55 menit) serta perlakuan antrean database yang perlu ditinjau. Patch ini terutama untuk memastikan transkripsi lokal terhubung dan berfungsi pada meeting pendek. Belum menjamin rapat 3 jam bisa selesai tanpa perubahan berikutnya pada worker dan strategi pemrosesan.
- Model `small` bisa kurang akurat untuk suara pelan, suara bertumpuk, atau nama barang. Uji audio dan periksa hasil notulen sebelum mengirim.
- Menjalankan Python beberapa kali untuk tiap segmen (10 menit) memuat model berulang. Optimasi ini dapat dilakukan pada tahap setelah uji awal.
- Pengiriman email tetap nonaktif sampai SMTP disetel.
- Model Whisper diunduh dari Hugging Face saat pertama dijalankan; sesudah tersimpan, inferensi berlangsung lokal.
- Tambahkan `/.venv-whisper/` ke `.gitignore` project jika menyimpan project di Git agar virtualenv tidak ikut di-commit.
