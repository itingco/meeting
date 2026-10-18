<?php
namespace App\Services;

use App\Models\Meeting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Creates a real PDF directly from notes; never depends on DOCX. */
class PdfMinutesService
{
    public function create(Meeting $meeting): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Dompdf belum terpasang. Jalankan: composer require dompdf/dompdf:^3.1');
        }
        if (!is_array($meeting->notes)) {
            throw new RuntimeException('Data notulen belum tersedia untuk dibuat PDF.');
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('tempDir', storage_path('app'));
        $options->set('fontCache', storage_path('app'));

        $pdf = new Dompdf($options);
        $html = view('pdf.minutes', ['meeting' => $meeting, 'notes' => $meeting->notes])->render();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $data = $pdf->output();
        if (!is_string($data) || !str_starts_with($data, '%PDF-') || strlen($data) < 800) {
            throw new RuntimeException('PDF notulen gagal dihasilkan atau tidak valid.');
        }

        $relative = 'meetings/'.$meeting->uuid.'/notulen-'.$meeting->started_at->format('Ymd-His').'.pdf';
        $storage = Storage::disk('local');
        $storage->makeDirectory(dirname($relative));
        if (!$storage->put($relative, $data)) {
            throw new RuntimeException('Gagal menyimpan file PDF notulen ke disk lokal.');
        }
        return $relative;
    }
}
