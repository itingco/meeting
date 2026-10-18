@extends('components.layout')
@section('title',$meeting->title)
@section('content')
<div class="page-head"><div><a class="backlink" href="{{ route('meetings.index') }}">← Dashboard</a><div class="eyebrow">RINCIAN MEETING</div><h1>{{ $meeting->title }}</h1><p class="muted">{{ $meeting->started_at->timezone(config('app.timezone'))->format('d F Y • H:i') }} • {{ $meeting->chunks_count }} bagian audio</p></div><span id="meeting-status" class="state state-{{ $meeting->status }}">{{ strtoupper($meeting->status) }}</span></div>
<div class="panel detail-panel">
<div class="section-heading"><h2>Progres otomatis</h2><div class="button-row">@if($meeting->document_path)<a class="button primary" href="{{ route('meetings.word',$meeting) }}">↓ Unduh Word</a>@endif @if($meeting->audio_path)<a class="button secondary" href="{{ route('meetings.audio',$meeting) }}">↓ Unduh Audio</a>@endif</div></div>
<div class="progress-highlight"><div><div class="eyebrow">TAHAP SAAT INI</div><h3 id="progress-stage">{{ $progress['stage_label'] }}</h3><p id="progress-hint">{{ $progress['hint'] }}</p></div><div class="progress-clock">Terakhir diperbarui: <strong id="progress-time">{{ $progress['stage_updated_at'] ?: 'Belum tersedia' }}</strong></div></div>
<div class="metrics progress-metrics"><div><span>ANTREAN AI</span><strong id="processing-queue">{{ $progress['processing_queue'] }} job</strong></div><div><span>BACKUP DRIVE</span><strong id="drive-progress">{{ $progress['drive_uploaded'] }}/{{ $progress['drive_total'] }} bagian</strong></div><div><span>ANTREAN DRIVE</span><strong id="drive-queue">{{ $progress['drive_queue'] }} job</strong></div></div>
<p class="muted mini" id="drive-hint">{{ $progress['drive_hint'] }}</p>
<div class="progress-steps">
@foreach(['queued','combining','transcribing','summarizing','document','email','completed'] as $key)
<div class="progress-step {{ $progress['stage']===$key ? 'is-current' : '' }}" data-stage="{{ $key }}"><span class="step-dot"></span><span>{{ $progress['steps'][$key] }}</span></div>
@endforeach
</div>
@if($meeting->status==='recording')<div class="alert warning">Rekaman masih berlangsung. Jika browser tertutup, buka kembali dashboard dari perangkat yang sama agar potongan yang tersimpan di browser dapat dipulihkan.</div>@endif
<div id="meeting-error" class="alert error {{ $meeting->error_message ? '' : 'hidden' }}">{{ $meeting->error_message }}</div>
<div id="drive-error" class="alert error {{ $progress['drive_error'] ? '' : 'hidden' }}">{{ $progress['drive_error'] ? 'Google Drive: '.$progress['drive_error'] : '' }}</div>
@if($canChange)
<div class="manual-actions"><div><b>Tindakan manual / pemulihan</b><p class="mini muted">Satu tombol untuk mencoba ulang backup Google Drive dan pembuatan notulen. Tidak menghapus rekaman.</p></div><div class="button-row">
<form method="post" action="{{ route('meetings.repair',$meeting) }}">@csrf<button class="button primary" type="submit" {{ $meeting->status==='recording' ? 'disabled' : '' }}>↻ Sinkronkan Drive &amp; Generate Notulen</button></form>
</div></div>
@endif
<div class="metrics"><div><span>STATUS EMAIL</span><strong id="email-state">{{ $meeting->emailed_at ? 'Terkirim' : (config('meeting.mail_enabled') ? 'Belum terkirim' : 'Nonaktif') }}</strong></div><div><span>STATUS FILE FINAL DRIVE</span><strong id="drive-state">{{ config('meeting.drive_enabled') ? ucfirst($meeting->drive_status) : 'Nonaktif' }}</strong></div><div><span>JUMLAH AUDIO</span><strong>{{ $meeting->chunks_count }}</strong></div></div>
@if($meeting->notes)
<div class="content-section"><h3>Ringkasan eksekutif</h3><p>{{ $meeting->notes['executive_summary'] ?? '—' }}</p></div>
<div class="content-section"><h3>Keputusan</h3><ul>@forelse($meeting->notes['decisions'] ?? [] as $line)<li>{{ $line }}</li>@empty<li>Belum disebutkan.</li>@endforelse</ul></div>
<div class="content-section"><h3>Action Items</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Tugas</th><th>PIC</th><th>Deadline</th></tr></thead><tbody>@forelse($meeting->notes['action_items'] ?? [] as $action)<tr><td>{{ $action['task'] ?? '—' }}</td><td>{{ $action['pic'] ?? '—' }}</td><td>{{ $action['deadline'] ?? '—' }}</td></tr>@empty<tr><td colspan="3">Belum disebutkan.</td></tr>@endforelse</tbody></table></div></div>
@endif
@if($meeting->transcript)<details class="transcript"><summary>Lihat transkrip lengkap</summary><pre>{{ $meeting->transcript }}</pre></details>@endif
<p class="mini muted">Diagnostik server: <code>storage/logs/processing-worker.log</code>, <code>storage/logs/drive-worker.log</code>, dan <code>storage/logs/laravel.log</code>. Progres halaman ini diperbarui setiap 5 detik.</p>
</div>
<script>
(()=>{
 const url=@json(route('meetings.status',$meeting)); let previousStatus=@json($meeting->status);
 const el=id=>document.getElementById(id);
 let busy=false;
 const poll=async()=>{
   if(busy)return; busy=true;
   try{
     const r=await fetch(url,{credentials:'same-origin',headers:{'Accept':'application/json'}});
     if(!r.ok)return;
     const d=await r.json(), p=d.progress;
     el('meeting-status').textContent=d.status.toUpperCase();
     el('meeting-status').className='state state-'+d.status;
     el('progress-stage').textContent=p.stage_label;
     el('progress-hint').textContent=p.hint;
     el('progress-time').textContent=p.stage_updated_at||'Belum tersedia';
     el('processing-queue').textContent=p.processing_queue+' job';
     el('drive-queue').textContent=p.drive_queue+' job';
     el('drive-progress').textContent=p.drive_uploaded+'/'+p.drive_total+' bagian';
     el('drive-hint').textContent=p.drive_hint;
     el('drive-state').textContent=d.drive_status;
     const err=el('meeting-error'); err.textContent=d.error||''; err.classList.toggle('hidden',!d.error);
     const derr=el('drive-error'); derr.textContent=p.drive_error?'Google Drive: '+p.drive_error:''; derr.classList.toggle('hidden',!p.drive_error);
     document.querySelectorAll('.progress-step').forEach(node=>node.classList.toggle('is-current',node.dataset.stage===p.stage));
     if(d.status==='completed' && previousStatus!=='completed')location.reload();
     previousStatus=d.status;
   }catch(e){}finally{busy=false;}
 };
 setInterval(poll,5000);
})();
</script>
@endsection
