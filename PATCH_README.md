# Patch 3 - SumoPod untuk penyusunan notulen (Laravel 10)

**Fungsi:** memisahkan provider transkripsi audio (OpenAI) dan penyusunan notulen (SumoPod). Source code tidak lagi memaksakan URL OpenAI untuk chat completions ketika provider SumoPod dipilih. Tidak mengubah migrasi, data, job, rekaman, SMTP, atau Google Drive.

## Instalasi
1. Cadangkan `app/Services/MeetingAiService.php`, `config/meeting.php`, dan `app/Console/Commands/MeetingDoctor.php` yang sedang dipakai.
2. Salin isi patch ini ke `C:\xampp82\htdocs\meeting` dan timpa 3 file yang sama.
3. Di `.env`, tambahkan (JANGAN masukkan token ke source code):

```dotenv
MEETING_SUMMARY_PROVIDER=sumopod
SUMOPOD_BASE_URL=https://ai.sumopod.com/v1
SUMOPOD_API_KEY="ISI_KEY_BARU_DARI_SUMOPOD"
SUMOPOD_SUMMARY_MODEL=claude-haiku-4.5
MEETING_DEMO_AI=false

# Masih diperlukan untuk mentranskripsi audio, bukan diganti key Sumopod:
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_API_KEY="ISI_OPENAI_API_KEY_DENGAN_SALDO"
OPENAI_TRANSCRIBE_MODEL=gpt-4o-mini-transcribe
```

4. Di terminal PowerShell:

```powershell
php artisan config:clear
php artisan queue:restart
php artisan meeting:doctor
```

## Batasan penting
- Sumopod menurut dokumentasi resminya (`https://docs.sumopod.com/guides/ai/openai-compatible-api`) hanya mendukung keluaran TEKS, bukan endpoint `/audio/transcriptions`.
- **Jika ingin benar-benar tanpa billing OpenAI, kita harus mengubah modul transkripsi lebih lanjut ke Whisper lokal atau penyedia Speech-to-Text lain.** Patch ini belum mengganti transkripsi. Jangan mengirim rekaman ke SumoPod audio endpoint.
- Token API Sumopod yang terlihat dalam screenshot pengguna perlu dicabut dan diterbitkan ulang.
- Model SumoPod harus cocok dengan daftar model di dashboard; `claude-haiku-4.5` dipilih sebagai contoh dari screenshot dashboard. Respons JSON dari model teks bisa tidak valid; jika demikian job gagal dan error tercatat di halaman progress.
- Sebelum produksi, tinjau kebijakan keamanan dan privasi provider karena rekaman rapat perusahaan akan diproses secara eksternal.
