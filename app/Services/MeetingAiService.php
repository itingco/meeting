<?php
namespace App\Services;
use App\Models\Meeting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;

class MeetingAiService
{
    private function apiKey(): string
    {
        $key = (string) config('services.openai.key');
        if (!$key) throw new RuntimeException('OPENAI_API_KEY belum disetel dalam .env');
        return $key;
    }
    public function transcribe(string $path): string
    {
        if (config('meeting.demo_ai')) {
            return '[MODE DEMO] Bukan transkripsi rekaman asli.';
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File audio transkripsi tidak ditemukan atau tidak bisa dibaca: '.basename($path));
        }

        $provider = strtolower(trim((string) config('meeting.transcription_provider', 'openai')));
        if ($provider === 'whisper') {
            return $this->transcribeWhisper($path);
        }
        if ($provider !== 'openai') {
            throw new RuntimeException('MEETING_TRANSCRIPTION_PROVIDER tidak dikenal: '.$provider);
        }

        // Fallback untuk instalasi yang tetap menggunakan OpenAI transkripsi.
        $handle = fopen($path, 'rb');
        if (!$handle) throw new RuntimeException('Tidak dapat membaca audio untuk transkripsi.');
        try {
            $response = Http::withToken($this->apiKey())->timeout(300)
                ->attach('file', $handle, basename($path))
                ->post(rtrim((string) config('meeting.openai_base_url'), '/') . '/audio/transcriptions', [
                    'model' => config('meeting.transcribe_model'), 'language' => 'id',
                ]);
            if (!$response->successful()) {
                throw new RuntimeException('API transkripsi gagal (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 400));
            }
            return trim((string) $response->json('text'));
        } finally {
            fclose($handle);
        }
    }

    private function transcribeWhisper(string $path): string
    {
        $python = trim((string) config('meeting.whisper_python'));
        $script = base_path('scripts/whisper_transcribe.py');
        if ($python === '') {
            throw new RuntimeException('WHISPER_PYTHON belum disetel.');
        }
        if (!is_file($script)) {
            throw new RuntimeException('Script Whisper tidak ada: scripts/whisper_transcribe.py');
        }

        $process = new Process([
            $python, $script,
            '--audio', $path,
            '--model', (string) config('meeting.whisper_model', 'small'),
            '--device', (string) config('meeting.whisper_device', 'cpu'),
            '--compute-type', (string) config('meeting.whisper_compute_type', 'int8'),
            '--language', (string) config('meeting.whisper_language', 'id'),
            '--cpu-threads', (string) config('meeting.whisper_cpu_threads', 6),
            '--beam-size', (string) config('meeting.whisper_beam_size', 3),
        ], base_path());
        $process->setTimeout((int) config('meeting.whisper_timeout', 1800));
        try {
            $process->run();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Whisper lokal berhenti atau melewati batas waktu: '.$exception->getMessage(), 0, $exception);
        }
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Whisper lokal gagal: '.mb_substr(trim($process->getErrorOutput()), 0, 1000));
        }
        $data = json_decode(trim($process->getOutput()), true);
        if (!is_array($data) || !is_string($data['text'] ?? null)) {
            throw new RuntimeException('Output transkripsi Whisper tidak valid. Periksa log worker processing.');
        }
        $text = trim($data['text']);
        if ($text === '') {
            throw new RuntimeException('Whisper tidak menemukan percakapan pada potongan audio. Cek mikrofon dan rekaman.');
        }
        return $text;
    }

    private function completion(string $prompt): string
    {
        $provider = strtolower((string) config('meeting.summary_provider', 'openai'));
        if ($provider === 'sumopod') {
            $key = trim((string) config('meeting.sumopod_api_key'));
            if ($key === '') throw new RuntimeException('SUMOPOD_API_KEY belum disetel dalam .env');
            $baseUrl = rtrim((string) config('meeting.sumopod_base_url'), '/');
            $model = (string) config('meeting.sumopod_summary_model');
        } elseif ($provider === 'openai') {
            $key = $this->apiKey();
            $baseUrl = rtrim((string) config('meeting.openai_base_url'), '/');
            $model = (string) config('meeting.summary_model');
        } else {
            throw new RuntimeException('MEETING_SUMMARY_PROVIDER tidak dikenali: '.$provider);
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Anda sekretaris rapat profesional Indonesia. Ringkas HANYA fakta eksplisit pada transkrip. Jangan menebak peserta, keputusan, tanggal, deadline, PIC, atau angka. Bila tidak jelas tulis "Belum disebutkan". Selalu balas JSON valid saja tanpa markdown.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        // Jangan kirim response_format ke SumoPod: tidak semua model provider mendukungnya.
        if ($provider === 'openai') {
            $payload['temperature'] = 0.1;
            $payload['response_format'] = ['type' => 'json_object'];
        }
        $response = Http::withToken($key)->timeout(240)
            ->post($baseUrl.'/chat/completions', $payload);
        if (!$response->successful()) {
            throw new RuntimeException('AI peringkasan '.$provider.' gagal (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 400));
        }
        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($content === '') throw new RuntimeException('AI peringkasan '.$provider.' mengembalikan teks kosong.');
        // Toleransi bila provider membungkus JSON dengan code fence markdown.
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/si', $content, $matches)) {
            $content = trim($matches[1]);
        }
        return $content;
    }

    public function makeNotes(Meeting $meeting, string $transcript): array
    {
        if (config('meeting.demo_ai')) return [
            'title' => $meeting->title.' (DEMO)',
            'executive_summary' => 'Contoh dokumen untuk uji alur. Tidak berdasarkan rekaman.',
            'participants' => [], 'agenda' => ['Contoh agenda'],
            'discussion' => [['topic' => 'Simulasi', 'summary' => 'AI dalam mode demo, tanpa transkripsi asli.']],
            'decisions' => ['Tidak ada keputusan nyata dalam mode demo'],
            'action_items' => [['task' => 'Aktifkan OpenAI API', 'pic' => 'Administrator', 'deadline' => 'Belum disebutkan']],
            'risks' => [], 'follow_up' => [],
        ];
        if (!trim($transcript)) throw new RuntimeException('Transkrip kosong. Pastikan audio dapat diputar.');
        // Hierarchical reduction menjaga rapat panjang tidak langsung melewati context model.
        $parts = preg_split('/(?<=\s)/u', $transcript, -1, PREG_SPLIT_NO_EMPTY);
        $segments = []; $current = '';
        foreach ($parts ?: [] as $part) {
            if (mb_strlen($current.$part) > 11000 && $current !== '') { $segments[] = $current; $current = ''; }
            $current .= $part;
        }
        if (trim($current)) $segments[] = $current;
        if (count($segments) > 1) {
            $intermediate = [];
            foreach ($segments as $n => $segment) {
                $intermediate[] = $this->completion('Buat JSON ringkasan fakta penting, keputusan, agenda, PIC dan tindak lanjut dari BAGIAN '.($n + 1).' saja. Pertahankan nama dan angka apa adanya.\n'.$segment);
            }
            $transcript = 'CATATAN TERSTRUKTUR PER BAGIAN (jangan hilangkan fakta):\n'.implode("\n", $intermediate);
        }
        $schema = '{"title":"string","executive_summary":"string","participants":["string"],"agenda":["string"],"discussion":[{"topic":"string","summary":"string"}],"decisions":["string"],"action_items":[{"task":"string","pic":"string","deadline":"string"}],"risks":["string"],"follow_up":["string"]}';
        $prompt = 'Buat notulen bahasa Indonesia format JSON persis sesuai skema ini: '.$schema."\nTanggal/waktu rapat: ".$meeting->started_at->timezone(config('app.timezone'))->format('d-m-Y H:i')." WIB\nJudul: ".$meeting->title."\nMateri:\n".$transcript;
        $result = json_decode($this->completion($prompt), true);
        if (!is_array($result)) throw new RuntimeException('AI mengembalikan JSON notulen tidak valid.');
        foreach (['participants', 'agenda', 'discussion', 'decisions', 'action_items', 'risks', 'follow_up'] as $key) {
            if (!isset($result[$key]) || !is_array($result[$key])) $result[$key] = [];
        }
        return $result;
    }
}
