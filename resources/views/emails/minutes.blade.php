<!doctype html><html lang="id"><body style="font-family:Arial,sans-serif;color:#24364b">
<h2 style="color:#142a42">Notulen Meeting – INGCO Indonesia</h2>
<p>Halo,</p><p>Terlampir hasil notulen otomatis meeting berikut:</p>
<table style="font-size:14px"><tr><td><b>Judul</b></td><td>: {{ $meeting->title }}</td></tr><tr><td><b>Tanggal</b></td><td>: {{ $meeting->started_at->timezone(config('app.timezone'))->format('d F Y, H:i') }} WIB</td></tr></table>
<p><b>Ringkasan:</b> {{ $meeting->notes['executive_summary'] ?? 'Lihat dokumen terlampir.' }}</p>
<p>Silakan periksa kembali hasil AI sebelum digunakan sebagai keputusan resmi.</p>
<p>Salam,<br><b>INGCO Meeting AI</b><br><small>Department IT • INGCO Indonesia</small></p>
</body></html>
