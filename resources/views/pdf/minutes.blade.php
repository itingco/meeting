<!doctype html>
<html lang="id">
<head><meta charset="UTF-8">
<style>
@page { margin: 23mm 18mm 19mm; }
body { font-family: 'DejaVu Sans', sans-serif; color: #1f3550; font-size: 9.1pt; line-height: 1.52; }
.brand { color: #c47b00; text-transform: uppercase; font-size: 8pt; letter-spacing: 1.4px; font-weight: bold; }
.header { border-bottom: 2px solid #efa400; padding-bottom: 13px; margin-bottom: 16px; }
h1 { font-size: 19pt; line-height: 1.18; margin: 5px 0; color: #132c49; }
.subtitle { font-size: 11pt; font-weight: bold; color: #1c3b57; }
.meta { width: 100%; border-collapse: collapse; margin: 0 0 16px; background: #f3f6fa; }
.meta td { padding: 6px 10px; vertical-align: top; border-bottom: 1px solid #e2e9f1; }
.meta td:first-child { width: 23%; color: #637991; font-size: 8.3pt; }
h2 { font-size: 11pt; margin: 17px 0 8px; color: #163c60; padding-bottom: 5px; border-bottom: 1px solid #dce5ee; }
p { margin: 0 0 8px; white-space: normal; overflow-wrap: break-word; }
ul { margin: 2px 0 9px 17px; padding: 0; }
li { margin: 0 0 5px; }
.topic { font-weight: bold; margin: 9px 0 3px; }
.grid { width: 100%; border-collapse: collapse; font-size: 8.4pt; margin-top: 5px; }
.grid th { background: #e9eff6; border: 1px solid #ccd7e4; padding: 8px 6px; text-align: left; }
.grid td { border: 1px solid #d7e0e8; padding: 7px 6px; vertical-align: top; word-wrap: break-word; }
.grid tr { page-break-inside: avoid; }
.note { font-size: 8pt; color: #647588; background: #fff8e8; border-left: 3px solid #efa400; padding: 9px 10px; margin-top: 20px; }
.footer { text-align: center; margin-top: 15px; font-size: 7.5pt; color: #8493a4; }
</style></head>
<body>
<div class="header"><div class="brand">INGCO INDONESIA / MEETING INTELLIGENCE</div>
<h1>NOTULEN RAPAT</h1><div class="subtitle">{{ $notes['title'] ?? $meeting->title }}</div></div>
<table class="meta">
<tr><td>Meeting</td><td>{{ $meeting->title }}</td></tr>
<tr><td>Mulai</td><td>{{ $meeting->started_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }} WIB</td></tr>
@if($meeting->ended_at)<tr><td>Selesai</td><td>{{ $meeting->ended_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }} WIB</td></tr>@endif
<tr><td>Referensi</td><td>{{ $meeting->uuid }}</td></tr>
</table>
<h2>Ringkasan Eksekutif</h2><p>{{ $notes['executive_summary'] ?? 'Belum disebutkan.' }}</p>
<h2>Peserta yang Disebutkan</h2><ul>@forelse($notes['participants'] ?? [] as $item)@if(is_scalar($item))<li>{{ $item }}</li>@endif @empty<li>Belum disebutkan.</li>@endforelse</ul>
<h2>Agenda</h2><ul>@forelse($notes['agenda'] ?? [] as $item)@if(is_scalar($item))<li>{{ $item }}</li>@endif @empty<li>Belum disebutkan.</li>@endforelse</ul>
<h2>Pokok Pembahasan</h2>
@forelse($notes['discussion'] ?? [] as $item)@if(is_array($item))<div class="topic">{{ $item['topic'] ?? 'Pembahasan' }}</div><p>{{ $item['summary'] ?? 'Belum disebutkan.' }}</p>@endif @empty<p>Belum disebutkan.</p>@endforelse
<h2>Keputusan Rapat</h2><ul>@forelse($notes['decisions'] ?? [] as $item)@if(is_scalar($item))<li>{{ $item }}</li>@endif @empty<li>Belum ada keputusan yang disebutkan.</li>@endforelse</ul>
<h2>Tindak Lanjut / Action Items</h2>
<table class="grid"><thead><tr><th style="width:6%">No</th><th style="width:50%">Tugas</th><th style="width:22%">PIC</th><th style="width:22%">Deadline</th></tr></thead><tbody>
@forelse($notes['action_items'] ?? [] as $index => $item)
@if(is_array($item))<tr><td>{{ $index + 1 }}</td><td>{{ $item['task'] ?? '-' }}</td><td>{{ $item['pic'] ?? 'Belum disebutkan' }}</td><td>{{ $item['deadline'] ?? 'Belum disebutkan' }}</td></tr>@endif
@empty<tr><td colspan="4">Belum ada tindak lanjut eksplisit.</td></tr>@endforelse
</tbody></table>
<h2>Risiko / Catatan</h2><ul>@forelse($notes['risks'] ?? [] as $item)@if(is_scalar($item))<li>{{ $item }}</li>@endif @empty<li>Belum disebutkan.</li>@endforelse</ul>
<h2>Rencana Follow Up</h2><ul>@forelse($notes['follow_up'] ?? [] as $item)@if(is_scalar($item))<li>{{ $item }}</li>@endif @empty<li>Belum disebutkan.</li>@endforelse</ul>
<div class="note">Disusun otomatis berdasarkan transkripsi AI. Verifikasi kembali nama, angka, PIC, tenggat, dan keputusan sebelum digunakan sebagai dokumen resmi.</div>
<div class="footer">INGCO Meeting AI - Internal &amp; Confidential</div>
</body></html>
