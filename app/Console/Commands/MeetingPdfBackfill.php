<?php
namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\PdfMinutesService;
use Illuminate\Console\Command;

class MeetingPdfBackfill extends Command
{
    protected $signature = 'meeting:pdf-backfill {--apply : Benar-benar membuat PDF untuk meeting lama}';
    protected $description = 'Membuat PDF dari data notulen lama tanpa AI dan tanpa mengirim ulang email.';

    public function handle(PdfMinutesService $pdf): int
    {
        $q = Meeting::query()->whereNotNull('notes')->where(function ($query) {
            $query->whereNull('document_path')->orWhere('document_path', 'not like', '%.pdf');
        });
        $total = (clone $q)->count();
        $this->info("Ada {$total} meeting yang memiliki notes tetapi belum PDF.");
        if (!$this->option('apply')) {
            $this->line('Ini hanya pratinjau. Tambahkan --apply untuk membuat PDF tanpa mengirim email ulang.');
            return self::SUCCESS;
        }
        $failed = 0; $done = 0;
        $q->orderBy('id')->chunkById(50, function ($meetings) use ($pdf, &$failed, &$done) {
            foreach ($meetings as $meeting) {
                try {
                    $newPath = $pdf->create($meeting);
                    $meeting->update([
                        'document_path' => $newPath,
                        'drive_status' => config('meeting.drive_enabled') ? 'pending' : $meeting->drive_status,
                    ]);
                    $this->line('PDF dibuat: '.$meeting->uuid); $done++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error($meeting->uuid.' : '.$e->getMessage());
                }
            }
        });
        $this->info("Selesai: {$done} berhasil, {$failed} gagal. Email lama tidak dikirim ulang. Sinkronkan Drive lewat tombol manual bila diperlukan.");
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
