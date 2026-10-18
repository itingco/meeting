<?php
namespace App\Console\Commands;

use App\Services\MeetingAiService;
use Illuminate\Console\Command;

class MeetingWhisperTest extends Command
{
    protected $signature = 'meeting:whisper-test {audio : Path absolut WAV/M4A/WebM untuk pengujian}';
    protected $description = 'Uji transkripsi melalui service Laravel tanpa antrean atau perubahan database';

    public function handle(MeetingAiService $ai): int
    {
        if (config('meeting.demo_ai')) {
            $this->error('MEETING_DEMO_AI=true. Ubah ke false untuk menguji transkripsi nyata.');
            return self::FAILURE;
        }
        if (config('meeting.transcription_provider') !== 'whisper') {
            $this->error('Set MEETING_TRANSCRIPTION_PROVIDER=whisper pada .env terlebih dahulu.');
            return self::FAILURE;
        }

        $path = (string) $this->argument('audio');
        if (!is_file($path) || !is_readable($path)) {
            $this->error('File tidak dapat dibaca: '.$path);
            return self::FAILURE;
        }

        $this->info('Menguji Whisper Lokal melalui Laravel...');
        try {
            $transcript = $ai->transcribe($path);
            $this->info('WHISPER LOKAL: BERHASIL');
            $this->line('Teks: '.mb_substr($transcript, 0, 800));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('WHISPER LOKAL: GAGAL - '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
