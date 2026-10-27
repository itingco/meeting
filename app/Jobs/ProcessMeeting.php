<?php
namespace App\Jobs;
use App\Mail\MeetingMinutesMail;
use App\Models\Meeting;
use App\Models\MeetingRecipient;
use App\Services\MeetingAiService;
use App\Services\PdfMinutesService;
use App\Support\MeetingProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use RuntimeException;
use Throwable;
class ProcessMeeting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 3300;
    private string $currentStage = 'queued';
    public function __construct(public int $meetingId) { $this->onQueue('processing'); }
    private function stage(Meeting $meeting, string $name): void
    {
        $this->currentStage=$name;
        $meeting->update(['process_stage'=>$name,'stage_updated_at'=>now()]);
        Log::info('Meeting AI progress',['meeting_id'=>$meeting->id,'uuid'=>$meeting->uuid,'stage'=>$name]);
    }
    public function handle(MeetingAiService $ai, PdfMinutesService $pdf): void
    {
        // Claim secara atomik: job lama/duplikat tidak boleh memproses meeting bersamaan.
        $claimed=Meeting::whereKey($this->meetingId)->where('status','queued')->update([
            'status'=>'processing', 'process_stage'=>'processing', 'stage_updated_at'=>now(),'error_message'=>null,
        ]);
        if (!$claimed) return;
        $meeting=Meeting::with(['chunks'=>fn($q)=>$q->orderBy('sequence')])->findOrFail($this->meetingId);
        try {
            $storage=Storage::disk('local'); $base='meetings/'.$meeting->uuid;
            $chunks=$meeting->chunks;
            if ($chunks->count() !== $meeting->expected_chunks || $chunks->isEmpty()) {
                throw new RuntimeException('Jumlah potongan audio tidak lengkap.');
            }
            if (!$meeting->audio_path || !$storage->exists($meeting->audio_path)) {
                $this->stage($meeting,'combining');
                $ext=str_contains((string)$meeting->mime_type,'mp4')?'mp4':(str_contains((string)$meeting->mime_type,'ogg')?'ogg':'webm');
                $storage->makeDirectory($base);

                /*
                 * Satu MediaRecorder menghasilkan chunk yang memang harus disambung byte-per-byte.
                 * Tetapi setelah browser/OS memutus mic (mis. lock/unlock), recorder baru akan membuat
                 * header container baru. Kita pecah menjadi beberapa capture session, gabungkan chunk
                 * di dalam tiap session secara binary, lalu concat session lengkap memakai FFmpeg.
                 *
                 * Ini tetap kompatibel dengan rekaman lama yang hanya memiliki satu MediaRecorder.
                 */
                $groups=[]; $current=[];
                foreach ($chunks as $index=>$chunk) {
                    if (!$storage->exists($chunk->local_path)) {
                        throw new RuntimeException('Potongan audio #'.$chunk->sequence.' hilang.');
                    }
                    $startsNewSession = $index===0 || $this->startsNewContainer(
                        $storage->path($chunk->local_path),
                        (string)$meeting->mime_type
                    );
                    if ($startsNewSession && $current) {
                        $groups[]=$current;
                        $current=[];
                    }
                    $current[]=$chunk;
                }
                if ($current) $groups[]=$current;

                $sessionFiles=[];
                foreach ($groups as $groupIndex=>$group) {
                    $sessionRelative=$base.'/capture-session-'.str_pad((string)$groupIndex,3,'0',STR_PAD_LEFT).'.'.$ext;
                    $output=fopen($storage->path($sessionRelative),'wb');
                    if (!$output) throw new RuntimeException('Gagal membuat file audio session.');
                    try {
                        foreach ($group as $chunk) {
                            $input=fopen($storage->path($chunk->local_path),'rb');
                            if (!$input) throw new RuntimeException('Gagal membaca potongan #'.$chunk->sequence);
                            try { stream_copy_to_stream($input,$output); } finally { fclose($input); }
                        }
                    } finally { fclose($output); }
                    $sessionFiles[]=$storage->path($sessionRelative);
                }

                $final=$base.'/rekaman-final.m4a';
                if (count($sessionFiles)===1) {
                    $this->ffmpeg([
                        '-nostdin','-hide_banner','-loglevel','error','-y',
                        '-i',$sessionFiles[0],
                        '-vn','-ac','1','-ar','16000','-c:a','aac','-b:a','48k',
                        $storage->path($final)
                    ]);
                } else {
                    $concatRelative=$base.'/capture-sessions.ffconcat';
                    $lines=['ffconcat version 1.0'];
                    foreach ($sessionFiles as $sessionFile) {
                        $normalized=str_replace('\\','/',$sessionFile);
                        $escaped=str_replace("'", "\\'",$normalized);
                        $lines[]="file '".$escaped."'";
                    }
                    $storage->put($concatRelative,implode(PHP_EOL,$lines).PHP_EOL);
                    Log::warning('Meeting recording recovered across multiple browser capture sessions',[
                        'meeting_id'=>$meeting->id,
                        'uuid'=>$meeting->uuid,
                        'sessions'=>count($sessionFiles),
                    ]);
                    $this->ffmpeg([
                        '-nostdin','-hide_banner','-loglevel','error','-y',
                        '-f','concat','-safe','0','-i',$storage->path($concatRelative),
                        '-vn','-ac','1','-ar','16000','-c:a','aac','-b:a','48k',
                        $storage->path($final)
                    ]);
                }
                $meeting->update(['audio_path'=>$final]);
            }
            if (!$meeting->transcript) {
                $this->stage($meeting,'transcribing');
                if (config('meeting.demo_ai')) {
                    $transcript=$ai->transcribe($storage->path($meeting->audio_path));
                } else {
                    $segmentDir=$base.'/segments'; $storage->makeDirectory($segmentDir);
                    $template=$storage->path($segmentDir.'/seg_%03d.m4a');
                    $this->ffmpeg(['-nostdin','-hide_banner','-loglevel','error','-y','-i',$storage->path($meeting->audio_path),'-f','segment','-segment_time','600','-reset_timestamps','1','-c','copy',$template]);
                    $files=glob($storage->path($segmentDir.'/seg_*.m4a')); sort($files);
                    if (!$files) throw new RuntimeException('Segmentasi audio gagal.');
                    $texts=[];
                    foreach ($files as $i=>$file) {
                        if (filesize($file)>24*1024*1024) throw new RuntimeException('Segment audio terlalu besar (>24 MB).');
                        $texts[]='[Bagian '.($i+1)."]\n".$ai->transcribe($file);
                        $this->stage($meeting,'transcribing');
                    }
                    $transcript=implode("\n\n",$texts);
                }
                $meeting->update(['transcript'=>$transcript]);
            }
            if (!$meeting->notes) {
                $this->stage($meeting,'summarizing');
                $meeting->update(['notes'=>$ai->makeNotes($meeting,$meeting->transcript)]);
            }
            if (!$meeting->document_path || !str_ends_with(strtolower($meeting->document_path), '.pdf') || !$storage->exists($meeting->document_path)) {
                $this->stage($meeting,'document');
                $meeting->update(['document_path'=>$pdf->create($meeting)]);
            }
            if (config('meeting.mail_enabled') && !$meeting->emailed_at) {
                $this->stage($meeting,'email');
                $recipients=[config('meeting.initial_recipient')];
                if (config('meeting.recipient_routing_enabled')) {
                    foreach (['CEO','GM'] as $role) {
                        $target=MeetingRecipient::where('department',$role)->first();
                        if (!$target?->email) throw new RuntimeException('Alamat email '.$role.' wajib diisi sebelum routing diaktifkan.');
                        $recipients[]=$target->email;
                    }
                    $recipients=array_merge($recipients,MeetingRecipient::where('enabled',true)->where('is_executive',false)->whereNotNull('email')->pluck('email')->all());
                }
                Mail::to(array_values(array_unique(array_filter($recipients))))->send(new MeetingMinutesMail($meeting));
                $meeting->update(['emailed_at'=>now()]);
            }
            $meeting->update(['status'=>'completed','error_message'=>null]);
            $this->stage($meeting,'completed');
            if (config('meeting.drive_enabled')) UploadArtifactsToDrive::dispatch($meeting->id);
        } catch (Throwable $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }
    private function startsNewContainer(string $path, string $mime): bool
    {
        $handle=fopen($path,'rb');
        if (!$handle) return false;
        try { $head=fread($handle,16); } finally { fclose($handle); }
        if (!is_string($head) || $head==='') return false;

        if (str_contains($mime,'webm')) {
            return str_starts_with($head,"\x1A\x45\xDF\xA3");
        }
        if (str_contains($mime,'mp4') || str_contains($mime,'x-m4a')) {
            return strlen($head)>=8 && substr($head,4,4)==='ftyp';
        }

        // Ogg pages can all begin with OggS, so the signature alone cannot safely
        // distinguish a new MediaRecorder session from the next page in the same one.
        return false;
    }
    private function recordFailure(Throwable $e): void
    {
        $label=MeetingProgress::label($this->currentStage);
        Meeting::whereKey($this->meetingId)->update([
            'status'=>'failed', 'process_stage'=>'failed','stage_updated_at'=>now(),
            'error_message'=>mb_substr('Gagal saat '.$label.': '.$e->getMessage(),0,1200),
        ]);
        Log::error('Meeting AI processing error',['meeting_id'=>$this->meetingId,'stage'=>$this->currentStage,'error'=>$e->getMessage()]);
    }
    public function failed(?Throwable $e): void
    {
        $meeting=Meeting::find($this->meetingId);
        if ($meeting && $meeting->status !== 'failed') $this->recordFailure($e ?: new RuntimeException('Worker gagal. Periksa laravel.log.'));
    }
    private function ffmpeg(array $args): void
    {
        $process=new Process(array_merge([config('meeting.ffmpeg')],$args));
        $process->setTimeout(1800); $process->run();
        if (!$process->isSuccessful()) throw new RuntimeException('FFmpeg gagal: '.mb_substr($process->getErrorOutput(),0,600));
    }
}
