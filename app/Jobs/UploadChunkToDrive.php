<?php
namespace App\Jobs;
use App\Models\MeetingChunk;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
class UploadChunkToDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=5;
    public int $timeout=240;
    public array $backoff=[15,60,180,300];
    public function __construct(public int $chunkId) { $this->onQueue('drive'); }
    public function handle(GoogleDriveService $drive): void
    {
        if (!config('meeting.drive_enabled')) return;
        $chunk=MeetingChunk::with('meeting')->findOrFail($this->chunkId);
        if ($chunk->drive_file_id) return;
        try {
            $chunk->update(['drive_status'=>'uploading','drive_error'=>null]);
            $ext=str_contains($chunk->mime_type,'mp4')?'mp4':(str_contains($chunk->mime_type,'ogg')?'ogg':'webm');
            $id=$drive->upload($chunk->meeting,Storage::disk('local')->path($chunk->local_path),
                'chunk_'.str_pad((string)$chunk->sequence,6,'0',STR_PAD_LEFT).'.'.$ext,$chunk->mime_type);
            $chunk->update(['drive_file_id'=>$id,'drive_status'=>'uploaded','drive_error'=>null]);
        } catch (Throwable $e) {
            $chunk->update(['drive_status'=>'failed','drive_error'=>mb_substr($e->getMessage(),0,500)]);
            Log::error('Meeting AI Drive chunk gagal',['chunk_id'=>$chunk->id,'meeting_id'=>$chunk->meeting_id,'error'=>$e->getMessage()]);
            throw $e;
        }
    }
}
