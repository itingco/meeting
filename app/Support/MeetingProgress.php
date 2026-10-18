<?php
namespace App\Support;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;
class MeetingProgress
{
    public const STEPS = [
        'recording' => 'Merekam audio di perangkat',
        'queued' => 'Menunggu worker pemrosesan',
        'processing' => 'Worker memulai pemrosesan',
        'combining' => 'Menggabungkan potongan audio',
        'transcribing' => 'AI sedang mentranskripsi rekaman',
        'summarizing' => 'AI menyusun notulen',
        'document' => 'Membuat dokumen Word',
        'email' => 'Mengirim email notulen',
        'completed' => 'Notulen selesai',
        'failed' => 'Proses mengalami kegagalan',
    ];
    public static function label(?string $stage): string
    {
        return self::STEPS[$stage ?? ''] ?? 'Memeriksa status proses';
    }
    public static function snapshot(Meeting $meeting): array
    {
        $uploaded = $meeting->chunks()->where('drive_status', 'uploaded')->count();
        $chunks = $meeting->chunks()->count();
        $failed = $meeting->chunks()->where('drive_status','failed')->count();
        $lastError = $meeting->chunks()->whereNotNull('drive_error')->orderByDesc('updated_at')->value('drive_error');
        $processingQueue = (int) DB::table('jobs')->where('queue','processing')->count();
        $driveQueue = (int) DB::table('jobs')->where('queue','drive')->count();
        $stage = $meeting->process_stage ?: $meeting->status;
        $idleSeconds = $meeting->stage_updated_at ? max(0, now()->diffInSeconds($meeting->stage_updated_at)) : null;
        $hint = match (true) {
            $meeting->status === 'queued' && $processingQueue > 0 => 'Menunggu worker processing. Jika lama tidak berubah, periksa Scheduled Task dan log processing-worker.log.',
            $meeting->status === 'queued' => 'Tidak ada job processing yang terdeteksi. Gunakan tombol Generate Notulen untuk menjadwalkan ulang.',
            $meeting->status === 'processing' => 'Worker memproses. Jika tahap ini tidak berubah lama, cek storage/logs/processing-worker.log dan laravel.log.',
            $meeting->status === 'failed' => 'Baca penyebab kegagalan di bawah, perbaiki konfigurasi, lalu gunakan Generate Notulen.',
            $meeting->status === 'completed' => 'Word selesai. Status email dan backup Drive ditampilkan terpisah.',
            default => 'Rekaman masih berlangsung atau menunggu penyelesaian.',
        };
        if (!config('meeting.drive_enabled')) $driveHint = 'Backup Drive nonaktif pada .env.';
        elseif ($failed) $driveHint = 'Ada '.$failed.' bagian gagal upload. Lihat error Drive dan gunakan Sinkronkan Drive.';
        elseif ($uploaded < $chunks) $driveHint = 'Backup Drive '.$uploaded.'/'.$chunks.' bagian; antrean Drive '.$driveQueue.' job.';
        elseif ($chunks > 0) $driveHint = 'Semua '.$chunks.' bagian audio berhasil diupload ke Drive. File final/Word diproses terpisah.';
        else $driveHint = 'Belum ada bagian audio di server.';
        return [
            'stage' => $stage,
            'stage_label' => self::label($stage),
            'stage_updated_at' => $meeting->stage_updated_at?->timezone(config('app.timezone'))->format('d-m-Y H:i:s'),
            'stage_idle_seconds' => $idleSeconds,
            'hint' => $hint,
            'processing_queue' => $processingQueue,
            'drive_queue' => $driveQueue,
            'drive_uploaded' => $uploaded,
            'drive_total' => $chunks,
            'drive_failed' => $failed,
            'drive_error' => $meeting->drive_error ?: $lastError,
            'drive_hint' => $driveHint,
            'steps' => self::STEPS,
        ];
    }
}
