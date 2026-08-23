<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SECOND BRAIN — pencarian web.
 *
 * Dipakai HANYA untuk pertanyaan pengetahuan umum yang memang bukan urusan
 * database tenant (mis. "cara menekan food cost", "harga pasar ayam sekarang").
 *
 * Sengaja BUKAN cadangan otomatis ketika database mengembalikan hasil kosong.
 * Pertanyaan "menu apa yang stoknya kosong?" yang dijawab "tidak ada" adalah
 * jawaban BENAR dan kabar baik — bila dialihkan ke web, AI akan mengarang
 * jawaban dari internet untuk pertanyaan tentang toko yang tak dikenal internet.
 *
 * Bila kunci pencarian kosong, fungsi ini mengembalikan penanda tidak-tersedia
 * dan AI akan berkata tidak tahu — jauh lebih baik daripada menebak.
 */
class WebSearch
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('ai.search');
    }

    public function siap(): bool
    {
        return ! empty($this->cfg['key']) && ($this->cfg['provider'] ?? 'none') !== 'none';
    }

    /** Skema fungsi pencarian untuk model. */
    public static function skema(): array
    {
        return [[
            'type' => 'function',
            'function' => [
                'name' => 'cari_web',
                'description' => 'Cari informasi UMUM di internet. Gunakan HANYA untuk pengetahuan yang tidak mungkin ada di database toko '
                    . '(misalnya tips manajemen restoran, tren kuliner, harga pasar bahan). '
                    . 'JANGAN gunakan untuk pertanyaan tentang data toko sendiri (penjualan, stok, menu, HPP) — '
                    . 'untuk itu selalu pakai fungsi database, dan bila hasilnya kosong sampaikan bahwa datanya memang kosong.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'kueri' => ['type' => 'string', 'description' => 'Kata kunci pencarian'],
                    ],
                    'required' => ['kueri'],
                ],
            ],
        ]];
    }

    public function cari(string $kueri): array
    {
        if (! $this->siap()) {
            return [
                'tersedia' => false,
                'catatan'  => 'Pencarian web belum diaktifkan di server ini. Sampaikan bahwa Anda tidak punya akses internet untuk pertanyaan ini, jangan menebak jawabannya.',
                'hasil'    => [],
            ];
        }

        $maks = (int) $this->cfg['max'];

        try {
            $hasil = match ($this->cfg['provider']) {
                'brave'  => $this->brave($kueri, $maks),
                default  => $this->serper($kueri, $maks),
            };
        } catch (\Throwable $e) {
            Log::warning('Pencarian web gagal: ' . $e->getMessage());
            return ['tersedia' => false, 'catatan' => 'Pencarian web sedang gagal.', 'hasil' => []];
        }

        return [
            'tersedia' => true,
            'kueri'    => $kueri,
            'catatan'  => $hasil === []
                ? 'Tidak ada hasil. Sampaikan bahwa Anda tidak menemukan informasinya.'
                : 'Sebutkan sumbernya saat menjawab.',
            'hasil'    => $hasil,
        ];
    }

    private function serper(string $kueri, int $maks): array
    {
        $r = Http::withHeaders(['X-API-KEY' => $this->cfg['key']])
            ->timeout((int) $this->cfg['timeout'])
            ->post('https://google.serper.dev/search', ['q' => $kueri, 'gl' => 'id', 'hl' => 'id', 'num' => $maks]);

        if (! $r->successful()) {
            return [];
        }

        return collect($r->json('organic') ?? [])->take($maks)->map(fn ($x) => [
            'judul'   => $x['title'] ?? '',
            'ringkas' => $x['snippet'] ?? '',
            'sumber'  => $x['link'] ?? '',
        ])->values()->all();
    }

    private function brave(string $kueri, int $maks): array
    {
        $r = Http::withHeaders([
            'X-Subscription-Token' => $this->cfg['key'],
            'Accept' => 'application/json',
        ])->timeout((int) $this->cfg['timeout'])
          ->get('https://api.search.brave.com/res/v1/web/search', ['q' => $kueri, 'country' => 'ID', 'count' => $maks]);

        if (! $r->successful()) {
            return [];
        }

        return collect($r->json('web.results') ?? [])->take($maks)->map(fn ($x) => [
            'judul'   => $x['title'] ?? '',
            'ringkas' => strip_tags($x['description'] ?? ''),
            'sumber'  => $x['url'] ?? '',
        ])->values()->all();
    }
}
