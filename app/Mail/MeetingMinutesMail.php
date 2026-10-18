<?php
namespace App\Mail;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
class MeetingMinutesMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Meeting $meeting) {}
    public function build(): self
    {
        if (!$this->meeting->document_path || !str_ends_with(strtolower($this->meeting->document_path), '.pdf')) {
            throw new \RuntimeException('Lampiran PDF belum tersedia untuk meeting ini.');
        }
        return $this->subject('Notulen Rapat: '.$this->meeting->title.' | '.$this->meeting->started_at->format('d-m-Y'))
            ->view('emails.minutes')
            ->attachFromStorageDisk('local', $this->meeting->document_path, 'Notulen-'.$this->meeting->started_at->format('Ymd').'.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
