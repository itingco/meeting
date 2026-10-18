<?php
namespace App\Services;
use App\Models\Meeting;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
class WordMinutesService
{
    public function create(Meeting $meeting): string
    {
        $notes = $meeting->notes ?: [];
        $word = new PhpWord();
        $word->setDefaultFontName('Calibri'); $word->setDefaultFontSize(10);
        $word->setDefaultParagraphStyle(['spaceAfter' => 150, 'lineHeight' => 1.2]);
        $word->addTitleStyle(1, ['bold' => true, 'size' => 20, 'color' => '172B4D'], ['spaceAfter' => 200]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 12, 'color' => '172B4D'], ['spaceBefore' => 220, 'spaceAfter' => 80]);
        $section = $word->addSection(['marginTop' => 1000, 'marginBottom' => 1000, 'marginLeft' => 1050, 'marginRight' => 1050]);
        $section->addText('INGCO INDONESIA  |  MEETING INTELLIGENCE', ['bold' => true, 'size' => 9, 'color' => 'D88700']);
        $section->addTitle('NOTULEN RAPAT', 1);
        $section->addText($meeting->title, ['bold' => true, 'size' => 13]);
        $section->addText('Tanggal: '.$meeting->started_at->timezone(config('app.timezone'))->format('d F Y, H:i').' WIB');
        if ($meeting->ended_at) $section->addText('Selesai: '.$meeting->ended_at->timezone(config('app.timezone'))->format('H:i').' WIB');
        $section->addText('ID: '.$meeting->uuid, ['size' => 8, 'color' => '777777']);
        $this->paragraph($section, 'Ringkasan Eksekutif', $notes['executive_summary'] ?? 'Tidak disebutkan');
        $this->bullets($section, 'Peserta yang Terdengar Disebutkan', $notes['participants'] ?? []);
        $this->bullets($section, 'Agenda', $notes['agenda'] ?? []);
        $section->addTitle('Pokok Pembahasan', 2);
        foreach (($notes['discussion'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $section->addText((string) ($item['topic'] ?? 'Pembahasan'), ['bold' => true]);
            $section->addText((string) ($item['summary'] ?? '-'));
        }
        $this->bullets($section, 'Keputusan Rapat', $notes['decisions'] ?? []);
        $section->addTitle('Tindak Lanjut / Action Items', 2);
        $table = $section->addTable(['borderSize' => 5, 'borderColor' => 'D9E0E8', 'cellMargin' => 90]);
        $widths = [500, 5100, 1600, 1700];
        $table->addRow();
        foreach (['No', 'Tugas', 'PIC', 'Deadline'] as $i => $head) {
            $table->addCell($widths[$i], ['bgColor' => 'F3F6FA'])->addText($head, ['bold' => true, 'size' => 9]);
        }
        foreach (($notes['action_items'] ?? []) as $index => $item) {
            if (!is_array($item)) continue;
            $table->addRow();
            foreach ([(string) ($index+1), (string) ($item['task'] ?? '-'), (string) ($item['pic'] ?? 'Belum disebutkan'), (string) ($item['deadline'] ?? 'Belum disebutkan')] as $i => $cell) {
                $table->addCell($widths[$i])->addText($cell, ['size' => 9]);
            }
        }
        if (empty($notes['action_items'])) { $table->addRow(); $table->addCell(8900, ['gridSpan' => 4])->addText('Belum ada tindak lanjut eksplisit.'); }
        $this->bullets($section, 'Risiko / Catatan', $notes['risks'] ?? []);
        $this->bullets($section, 'Rencana Follow Up', $notes['follow_up'] ?? []);
        $section->addText('Disusun otomatis oleh AI dari rekaman. Mohon periksa kembali akurasi isi, nama, angka, dan keputusan sebelum didistribusikan.', ['italic' => true, 'size' => 8, 'color' => '666666']);
        $footer = $section->addFooter();
        $footer->addText('INGCO Meeting AI  •  Internal & Confidential', ['size' => 8, 'color' => '777777'], ['alignment' => Jc::CENTER]);
        $relative = 'meetings/'.$meeting->uuid.'/notulen-'.$meeting->started_at->format('Ymd-His').'.docx';
        Storage::disk('local')->makeDirectory(dirname($relative));
        $word->save(Storage::disk('local')->path($relative), 'Word2007');
        return $relative;
    }
    private function paragraph($section, string $heading, string $value): void
    {
        $section->addTitle($heading, 2); $section->addText($value ?: 'Belum disebutkan');
    }
    private function bullets($section, string $heading, array $items): void
    {
        $section->addTitle($heading, 2);
        if (!$items) { $section->addText('Belum disebutkan'); return; }
        foreach ($items as $item) if (is_scalar($item)) $section->addListItem((string) $item, 0, null, ['listType' => 3]);
    }
}
