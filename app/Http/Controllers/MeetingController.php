<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessMeeting;
use App\Jobs\UploadArtifactsToDrive;
use App\Jobs\UploadChunkToDrive;
use App\Models\Meeting;
use App\Models\MeetingChunk;
use App\Support\MeetingProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
class MeetingController extends Controller
{
    private function mayRead(Request $request, Meeting $meeting): void
    {
        abort_unless($request->user()->can('view-history') || $meeting->user_id === $request->user()->id, 403);
    }
    private function mayChange(Request $request, Meeting $meeting): void
    {
        abort_unless($request->user()->is_admin || $meeting->user_id === $request->user()->id, 403);
    }
    public function index(Request $request)
    {
        $historyAccess = $request->user()->can('view-history');
        return view('meetings.index', [
            'historyAccess'=>$historyAccess,
            'meetings'=>$historyAccess ? Meeting::orderByDesc('started_at')->paginate(15) : null,
            'demo'=>config('meeting.demo_ai'),
        ]);
    }
    public function store(Request $request)
    {
        $input = $request->validate(['title' => 'nullable|string|max:180']);
        $meeting = Meeting::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'title' => trim($input['title'] ?? '') ?: 'Meeting '.now()->format('d-m-Y H:i'),
            'status' => 'recording', 'process_stage'=>'recording', 'stage_updated_at'=>now(), 'started_at' => now(),
        ]);
        return response()->json(['uuid' => $meeting->uuid, 'url' => route('meetings.show', $meeting)], 201);
    }
    public function show(Request $request, Meeting $meeting)
    {
        $this->mayRead($request, $meeting);
        $meeting->loadCount('chunks');
        return view('meetings.show', ['meeting'=>$meeting, 'progress'=>MeetingProgress::snapshot($meeting),
            'canChange'=>$request->user()->is_admin || $meeting->user_id === $request->user()->id]);
    }
    public function status(Request $request, Meeting $meeting)
    {
        $this->mayRead($request, $meeting);
        return response()->json([
            'uuid' => $meeting->uuid, 'status' => $meeting->status,
            'chunks' => $meeting->chunks()->count(), 'expected_chunks' => $meeting->expected_chunks,
            'drive_status' => $meeting->drive_status, 'emailed_at' => $meeting->emailed_at?->toIso8601String(),
            'error' => $meeting->error_message,
            'document_url' => $meeting->document_path ? route('meetings.word', $meeting) : null,
            'meeting_url' => route('meetings.show', $meeting),
            'progress' => MeetingProgress::snapshot($meeting),
        ]);
    }
    public function uploadChunk(Request $request, Meeting $meeting)
    {
        $this->mayChange($request, $meeting);
        if ($meeting->status !== 'recording') return response()->json(['error' => 'Meeting bukan status recording.'], 409);
        $request->validate(['sequence'=>'required|integer|min:0|max:100000', 'audio'=>'required|file|max:8192', 'mime_type'=>'required|string|max:100']);
        $mime = $request->string('mime_type')->toString();
        if (!preg_match('#^(audio/(webm|mp4|ogg|mpeg|x-m4a)|video/webm)(;.*)?$#i', $mime)) {
            return response()->json(['error' => 'Jenis audio tidak didukung.'], 422);
        }
        $sequence = (int) $request->input('sequence');
        $existing = MeetingChunk::where('meeting_id', $meeting->id)->where('sequence', $sequence)->first();
        if ($existing) return response()->json(['ok'=>true,'sequence'=>$sequence,'duplicate'=>true]);
        $path = 'meetings/'.$meeting->uuid.'/chunks/'.str_pad((string)$sequence,6,'0',STR_PAD_LEFT).'.part';
        $uploaded = $request->file('audio');
        if (!Storage::disk('local')->put($path, fopen($uploaded->getRealPath(),'rb'))) {
            throw new RuntimeException('Tidak dapat menyimpan chunk audio.');
        }
        try {
            DB::transaction(function () use ($meeting,$sequence,$mime,$path,$uploaded) {
                $locked=Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
                if ($locked->status!=='recording') throw new RuntimeException('Rekaman sudah ditutup.');
                if ($locked->mime_type && $locked->mime_type!==$mime) throw new RuntimeException('Codec berubah di tengah rekaman. Mohon buat meeting baru.');
                if (!$locked->mime_type) $locked->update(['mime_type'=>$mime]);
                MeetingChunk::firstOrCreate(['meeting_id'=>$meeting->id,'sequence'=>$sequence], [
                    'mime_type'=>$mime, 'local_path'=>$path, 'size_bytes'=>$uploaded->getSize(),
                    'drive_status'=>config('meeting.drive_enabled') ? 'pending' : 'disabled',
                ]);
            });
        } catch (\Throwable $e) { return response()->json(['error'=>$e->getMessage()],409); }
        if (config('meeting.drive_enabled')) {
            $chunk=MeetingChunk::where('meeting_id',$meeting->id)->where('sequence',$sequence)->first();
            UploadChunkToDrive::dispatch($chunk->id);
        }
        return response()->json(['ok'=>true,'sequence'=>$sequence]);
    }
    public function finish(Request $request, Meeting $meeting)
    {
        $this->mayChange($request, $meeting);
        $request->validate(['expected_chunks'=>'required|integer|min:1|max:100001']);
        $expected=(int)$request->input('expected_chunks');
        if ($meeting->status!=='recording') return response()->json(['ok'=>true,'status'=>$meeting->status]);
        $count=$meeting->chunks()->count();
        $min=$meeting->chunks()->min('sequence'); $max=$meeting->chunks()->max('sequence');
        if ($count!==$expected || (int)$min!==0 || (int)$max!==$expected-1) {
            return response()->json(['error'=>"Chunk belum lengkap. Server: {$count}/{$expected}. Coba sinkronkan ulang saat internet stabil."],409);
        }
        $updated=Meeting::whereKey($meeting->id)->where('status','recording')->update([
            'status'=>'queued','process_stage'=>'queued','stage_updated_at'=>now(),
            'ended_at'=>now(),'expected_chunks'=>$expected,
        ]);
        if ($updated) ProcessMeeting::dispatch($meeting->id);
        return response()->json(['ok'=>true,'status'=>'queued']);
    }
    public function repair(Request $request, Meeting $meeting)
    {
        $this->mayChange($request, $meeting);
        if ($meeting->status === 'recording') {
            return back()->with('error', 'Selesaikan perekaman terlebih dahulu.');
        }
        $messages=[];
        if (config('meeting.drive_enabled')) {
            $chunksQueued=0; $missing=0;
            foreach ($meeting->chunks()->whereNull('drive_file_id')->get() as $chunk) {
                if (!Storage::disk('local')->exists($chunk->local_path)) { $missing++; continue; }
                $chunk->update(['drive_status'=>'pending','drive_error'=>null]);
                UploadChunkToDrive::dispatch($chunk->id);
                $chunksQueued++;
            }
            if ($meeting->audio_path || $meeting->document_path) {
                $meeting->update(['drive_status'=>'pending','drive_error'=>null]);
                UploadArtifactsToDrive::dispatch($meeting->id);
            }
            $messages[]="Drive: {$chunksQueued} potongan dijadwalkan".($missing ? ", {$missing} file lokal hilang" : '');
        } else $messages[]='Drive belum aktif pada .env';

        if ($meeting->status === 'completed' && $meeting->document_path) {
            $messages[]='Notulen sudah tersedia';
        } elseif (!config('meeting.demo_ai') && !config('services.openai.key')) {
            $messages[]='AI belum dijadwalkan: OPENAI_API_KEY belum diisi';
        } elseif ($meeting->status==='processing' && $meeting->stage_updated_at && $meeting->stage_updated_at->gt(now()->subMinutes(60))) {
            $messages[]='AI masih berjalan; tidak dijadwalkan duplikat';
        } elseif (!$meeting->chunks()->exists()) {
            $messages[]='AI belum dijadwalkan: file rekaman belum ada';
        } else {
            $meeting->update(['status'=>'queued','process_stage'=>'queued','stage_updated_at'=>now(),'error_message'=>null]);
            ProcessMeeting::dispatch($meeting->id);
            $messages[]='Generate Notulen masuk antrean processing';
        }
        return back()->with('message',implode('. ', $messages).'. Pantau progres pada halaman ini.');
    }
    public function generate(Request $request, Meeting $meeting)
    {
        $this->mayChange($request, $meeting);
        if ($meeting->status==='recording') return back()->with('error','Akhiri perekaman terlebih dahulu.');
        if ($meeting->status==='completed' && $meeting->document_path) return back()->with('message','Notulen sudah selesai. Gunakan tombol unduh Word.');
        if (!$meeting->chunks()->exists()) return back()->with('error','Belum ada potongan rekaman di server.');
        if (!config('meeting.demo_ai') && !config('services.openai.key')) {
            return back()->with('error','OPENAI_API_KEY belum diisi di .env. Generation belum dapat berjalan.');
        }
        if ($meeting->status==='processing' && $meeting->stage_updated_at && $meeting->stage_updated_at->gt(now()->subMinutes(60))) {
            return back()->with('error','Worker masih dalam proses. Periksa tahap dan log sebelum menjadwalkan ulang.');
        }
        $meeting->update(['status'=>'queued','process_stage'=>'queued','stage_updated_at'=>now(),'error_message'=>null]);
        ProcessMeeting::dispatch($meeting->id);
        return back()->with('message','Generate Notulen dijadwalkan. Pantau tahap proses di halaman ini.');
    }
    public function syncDrive(Request $request, Meeting $meeting)
    {
        $this->mayChange($request, $meeting);
        if (!config('meeting.drive_enabled')) return back()->with('error','Aktifkan GOOGLE_DRIVE_ENABLED=true terlebih dahulu.');
        $count=0; $missing=0;
        foreach ($meeting->chunks()->whereNull('drive_file_id')->get() as $chunk) {
            if (!Storage::disk('local')->exists($chunk->local_path)) { $missing++; continue; }
            $chunk->update(['drive_status'=>'pending','drive_error'=>null]);
            UploadChunkToDrive::dispatch($chunk->id); $count++;
        }
        if ($meeting->audio_path || $meeting->document_path) {
            // Ulangi pekerjaan untuk hasil final bila ada; drive bisa mendeteksi file remote dengan nama sama.
            $meeting->update(['drive_status'=>'pending','drive_error'=>null]);
            UploadArtifactsToDrive::dispatch($meeting->id);
        }
        $message="Sinkronisasi Drive dijadwalkan: {$count} bagian audio";
        if ($meeting->audio_path || $meeting->document_path) $message.=', termasuk pemeriksaan file final/Word';
        if ($missing) $message.=". Ada {$missing} file lokal yang hilang";
        return back()->with('message',$message.'. Pantau antrean dan progres Drive di bawah.');
    }
    public function word(Request $request, Meeting $meeting)
    {
        $this->mayRead($request, $meeting);
        if (!$meeting->document_path || !Storage::disk('local')->exists($meeting->document_path)) abort(404);
        return Storage::disk('local')->download($meeting->document_path,'Notulen-'.Str::slug($meeting->title).'.docx');
    }
    public function audio(Request $request, Meeting $meeting)
    {
        $this->mayRead($request, $meeting);
        if (!$meeting->audio_path || !Storage::disk('local')->exists($meeting->audio_path)) abort(404);
        return Storage::disk('local')->download($meeting->audio_path,'Rekaman-'.$meeting->started_at->format('Ymd-His').'.m4a');
    }
}
