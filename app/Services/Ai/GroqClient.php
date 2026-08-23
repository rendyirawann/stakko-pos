<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien Groq (format API kompatibel OpenAI).
 *
 * Sengaja tipis dan tanpa SDK: satu POST /chat/completions. Karena formatnya
 * standar, penyedia bisa ditukar hanya dengan mengubah GROQ_BASE_URL bila batas
 * gratis Groq tak lagi memadai — tanpa menyentuh Assistant maupun Prediksi.
 *
 * Kegagalan TIDAK pernah dilempar sebagai exception mentah ke pengguna. Yang
 * dikembalikan selalu bentuk yang sama, dengan `error` berisi kalimat yang bisa
 * dibaca pemilik toko — sebab pesan seperti "429 Too Many Requests" tidak
 * memberi tahu mereka harus berbuat apa.
 */
class GroqClient
{
    private string $key;
    private string $base;
    private array $cfg;

    public function __construct()
    {
        $this->cfg  = config('ai.groq');
        $this->key  = (string) ($this->cfg['key'] ?? '');
        $this->base = rtrim((string) $this->cfg['base'], '/');
    }

    public function siap(): bool
    {
        return $this->key !== '';
    }

    /**
     * Satu putaran percakapan.
     *
     * @param  array       $messages  riwayat format OpenAI
     * @param  array|null  $tools     skema fungsi; null = jawab tanpa alat
     * @return array{ok:bool,message:?array,error:?string,tokens_in:int,tokens_out:int}
     */
    public function chat(array $messages, ?array $tools = null, ?string $model = null, ?int $maxTokens = null): array
    {
        if (! $this->siap()) {
            return $this->gagal('Fitur AI belum diaktifkan: GROQ_API_KEY belum diisi di server.');
        }

        $body = [
            'model'       => $model ?: $this->cfg['model'],
            'messages'    => $messages,
            'temperature' => $this->cfg['temperature'],
            // Model reasoning butuh jatah lebih besar: sebagian habis untuk
            // berpikir sebelum jawabannya mulai ditulis.
            'max_tokens'  => $maxTokens ?: $this->cfg['max_tokens'],
        ];
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        try {
            $resp = Http::withToken($this->key)
                ->timeout((int) $this->cfg['timeout'])
                ->acceptJson()
                ->post($this->base . '/chat/completions', $body);
        } catch (\Throwable $e) {
            Log::warning('Groq tidak terjangkau: ' . $e->getMessage());
            return $this->gagal('Layanan AI sedang tidak dapat dihubungi. Coba lagi sebentar lagi.');
        }

        if ($resp->status() === 429) {
            return $this->gagal('Kuota AI sedang penuh (batas pemakaian bersama tercapai). Coba lagi beberapa menit.');
        }
        if ($resp->status() === 401) {
            return $this->gagal('Kunci API AI tidak berlaku. Hubungi administrator.');
        }
        if (! $resp->successful()) {
            $pesan = $resp->json('error.message') ?: ('HTTP ' . $resp->status());
            Log::warning('Groq gagal: ' . $pesan);
            // Pesan asli dari penyedia bisa menyebut nama model atau batas token;
            // itu informasi teknis, bukan untuk pemilik toko.
            return $this->gagal('Layanan AI menolak permintaan. Silakan coba pertanyaan yang lebih singkat.');
        }

        $json = $resp->json();
        $pesan = $json['choices'][0]['message'] ?? null;

        if (is_array($pesan) && isset($pesan['content'])) {
            $pesan['content'] = self::bersihkanPenalaran((string) $pesan['content']);
        }

        return [
            'ok'         => true,
            'message'    => $pesan,
            'error'      => null,
            'tokens_in'  => (int) ($json['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($json['usage']['completion_tokens'] ?? 0),
        ];
    }

    /**
     * Buang blok penalaran internal model.
     *
     * Model bertipe reasoning (mis. qwen3.6) kadang menuliskan proses berpikirnya
     * di dalam <think>...</think> — sering dalam bahasa Inggris — dan itu ikut
     * terkirim sebagai jawaban. Pemilik toko tidak boleh melihat itu.
     *
     * Blok yang belum tertutup ikut dibuang: bila jawaban terpotong di tengah
     * penalaran, yang tersisa hanyalah catatan internal, bukan jawaban.
     */
    public static function bersihkanPenalaran(string $teks): string
    {
        $teks = preg_replace('/<think>.*?<\/think>/is', '', $teks) ?? $teks;
        $teks = preg_replace('/<think>.*$/is', '', $teks) ?? $teks;
        // Beberapa model menutup tanpa membuka setelah dipotong di tengah.
        $teks = preg_replace('/^.*?<\/think>/is', '', $teks) ?? $teks;

        return trim($teks);
    }

    /** Judul singkat percakapan — dikerjakan model ringan agar tidak memakan kuota besar. */
    public function judul(string $pertanyaan): ?string
    {
        $r = $this->chat([
            ['role' => 'system', 'content' => 'Ringkas pertanyaan pengguna menjadi judul Bahasa Indonesia maksimal 6 kata. Jawab HANYA judulnya, tanpa tanda kutip.'],
            ['role' => 'user', 'content' => mb_substr($pertanyaan, 0, 500)],
        ], null, $this->cfg['model_ringan']);

        $judul = trim((string) ($r['message']['content'] ?? ''));

        return $judul !== '' ? mb_substr($judul, 0, 200) : null;
    }

    private function gagal(string $pesan): array
    {
        return ['ok' => false, 'message' => null, 'error' => $pesan, 'tokens_in' => 0, 'tokens_out' => 0];
    }
}
