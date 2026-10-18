<?php
namespace App\Jobs;
use App\Models\Meeting;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
class UploadArtifactsToDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=5;
    public int $timeout=240;
    public array $backoff=[30,90,180,360];
    public function __construct(public int $meetingId) { $this->onQueue('drive'); }
    public function handle(GoogleDriveService $drive): void
    {
        if (!config('meeting.drive_enabled')) return;
        $meeting=Meeting::findOrFail($this->meetingId);
        if ($meeting->drive_status==='uploaded') return;
        $meeting->update(['drive_status'=>'uploading','drive_error'=>null]);
        try {
            $storage=Storage::disk('local');
            if (!$meeting->audio_path && !$meeting->document_path) throw new RuntimeException('Belum ada file final/Word untuk di-upload.');
            if ($meeting->audio_path) $drive->upload($meeting,$storage->path($meeting->audio_path),'rekaman-final.m4a','audio/mp4');
            if ($meeting->document_path) $drive->upload($meeting,$storage->path($meeting->document_path),'notulen.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $meeting->update(['drive_status'=>'uploaded','drive_error'=>null]);
        } catch (Throwable $e) {
            $meeting->update(['drive_status'=>'failed','drive_error'=>mb_substr($e->getMessage(),0,600)]);
            Log::error('Meeting AI Drive final gagal',['meeting_id'=>$meeting->id,'error'=>$e->getMessage()]);
            throw $e;
        }
    }
}
