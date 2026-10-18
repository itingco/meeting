<?php
namespace App\Console\Commands;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
class MeetingDoctor extends Command
{
    protected $signature = 'meeting:doctor';
    protected $description = 'Periksa prasyarat aplikasi tanpa menampilkan credential rahasia';
    public function handle(GoogleDriveService $drive): int
    {
        $this->info('=== INGCO Meeting AI: system check ===');
        $this->line('PHP: '.PHP_VERSION);
        $this->line('Database: '.config('database.default'));
        $this->line('PDO driver tersedia: '.(in_array(config('database.default'), \PDO::getAvailableDrivers(), true) ? 'YA' : 'TIDAK'));
        $transcribeProvider = strtolower((string) config('meeting.transcription_provider', 'openai'));
        if ($transcribeProvider === 'whisper') {
            $python = (string) config('meeting.whisper_python');
            $script = base_path('scripts/whisper_transcribe.py');
            $this->line('Transkripsi: Whisper Lokal; model: '.config('meeting.whisper_model').'; device: '.config('meeting.whisper_device'));
            $this->line('Whisper Python: '.(is_file($python) ? 'ditemukan' : 'BELUM ditemukan').'; script: '.(is_file($script) ? 'tersedia' : 'BELUM tersedia'));
            if (is_file($python) && is_file($script)) {
                try {
                    $check = new Process([$python, '-c', 'import faster_whisper, av; print("OK")']);
                    $check->setTimeout(30);
                    $check->run();
                    $this->line('Library faster-whisper/PyAV: '.($check->isSuccessful() ? 'SIAP' : 'BELUM (cek virtualenv)'));
                } catch (\Throwable $e) {
                    $this->line('Library faster-whisper/PyAV: BELUM ('.$e->getMessage().')');
                }
            }
        } else {
            $this->line('Transkripsi: OpenAI; OPENAI_API_KEY: '.(config('services.openai.key') ? 'terpasang' : 'BELUM'));
        }
        if (config('meeting.demo_ai')) $this->warn('MODE DEMO AI AKTIF - transkripsi/notulen bukan data nyata');
        $provider = (string) config('meeting.summary_provider', 'openai');
        $ready = $provider === 'sumopod' ? (bool) config('meeting.sumopod_api_key') : (bool) config('services.openai.key');
        $this->line('Penyusun notulen: '.$provider.'; API key: '.($ready ? 'terpasang' : 'BELUM'));
        if ($provider === 'sumopod') $this->line('Model penyusun notulen: '.config('meeting.sumopod_summary_model'));
        $this->line('Google Drive: '.(config('meeting.drive_enabled') ? 'aktif' : 'nonaktif').'; token: '.($drive->connected() ? 'tersimpan' : 'belum tersambung'));
        $this->line('SMTP: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').'; password: '.(config('mail.mailers.smtp.password') ? 'terpasang' : 'BELUM'));
        $this->line('Email notulen: '.(config('meeting.mail_enabled') ? 'aktif' : 'nonaktif').'; tujuan: '.config('meeting.initial_recipient'));
        $this->line('Routing departemen: '.(config('meeting.recipient_routing_enabled') ? 'AKTIF' : 'nonaktif'));
        try {
            $proc = new Process([config('meeting.ffmpeg'), '-version']);
            $proc->setTimeout(10); $proc->run();
            $this->line('FFmpeg: '.($proc->isSuccessful() ? 'SIAP' : 'BELUM'));
        } catch (\Throwable $e) { $this->line('FFmpeg: BELUM ('.$e->getMessage().')'); }
        return self::SUCCESS;
    }
}
