<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * AI PREDIKSI — analisis periode + rekomendasi.
 *
 * Pembagian tugas yang dipegang ketat:
 *   - ANGKA  dihitung PostgreSQL lewat DataTools (deterministik, bisa diaudit).
 *   - NARASI disusun model dari angka itu.
 *
 * Model tidak pernah diminta menghitung. Kalau model diminta menjumlahkan
 * omzet, ia akan salah sesekali dengan sangat percaya diri — dan laporan
 * keuangan yang salah lebih buruk daripada tidak ada laporan.
 *
 * Semua angka pada laporan PDF diambil dari bundel `data`, bukan dari teks
 * model, sehingga tabel laporan tetap benar bahkan bila narasinya kurang tepat.
 */
class Prediksi
{
    private DataTools $tools;
    private GroqClient $ai;

    public function __construct(private Tenant $tenant)
    {
        $this->tools = new DataTools((int) $tenant->id);
        $this->ai = new GroqClient();
    }

    public function siap(): bool
    {
        return $this->ai->siap();
    }

    /**
     * Kumpulkan seluruh angka untuk satu periode. Tanpa AI — bagian ini murni
     * data, dan tetap berguna (serta tetap benar) meski AI mati.
     */
    public function data(string $dari, string $sampai): array
    {
        $rentang = ['dari' => $dari, 'sampai' => $sampai];

        return [
            'periode' => [
                'dari' => $dari,
                'sampai' => $sampai,
                'jumlah_hari' => Carbon::parse($dari)->diffInDays(Carbon::parse($sampai)) + 1,
                'label' => Carbon::parse($dari)->translatedFormat('d M Y') . ' – ' . Carbon::parse($sampai)->translatedFormat('d M Y'),
            ],
            'keuangan'      => $this->tools->jalankan('laba_rugi', $rentang),
            'terlaris_qty'  => $this->tools->jalankan('menu_terlaris', $rentang + ['urut' => 'qty', 'batas' => 10]),
            'terlaris_laba' => $this->tools->jalankan('menu_terlaris', $rentang + ['urut' => 'laba', 'batas' => 10]),
            'harian'        => $this->tools->jalankan('penjualan_harian', $rentang),
            'beban'         => $this->tools->jalankan('beban_per_kategori', $rentang),
            'pemakaian'     => $this->tools->jalankan('pemakaian_bahan', $rentang),
            'stok_menipis'  => $this->tools->jalankan('stok_menipis', []),
        ];
    }

    /**
     * Narasi analisis dari model. Mengembalikan null bila AI tidak tersedia —
     * pemanggil tetap bisa menampilkan/mencetak angkanya.
     *
     * @return array{ok:bool,teks:?string,error:?string,tokens:int}
     */
    public function narasi(array $data, ?string $pertanyaanTambahan = null): array
    {
        if (! $this->ai->siap()) {
            return ['ok' => false, 'teks' => null, 'error' => 'Fitur AI belum diaktifkan (GROQ_API_KEY belum diisi).', 'tokens' => 0];
        }

        $adaHpp = (bool) ($data['keuangan']['hpp_tercatat'] ?? false);
        $hariAdaJualan = (int) ($data['harian']['jumlah_hari_ada_penjualan'] ?? 0);

        $sistem = <<<TXT
        Anda analis bisnis untuk rumah makan "{$this->tenant->name}". Tulis analisis dalam Bahasa Indonesia.

        SUMBER SATU-SATUNYA adalah data JSON yang diberikan. JANGAN menambah angka apa pun yang tidak ada di sana,
        dan jangan menghitung ulang. Bila sebuah angka tidak ada, katakan datanya belum tersedia.

        KONDISI DATA PERIODE INI:
        - HPP tercatat: {$this->ya($adaHpp)}
        - Jumlah hari yang ada penjualan: {$hariAdaJualan}

        ATURAN:
        1. Bila HPP tidak tercatat, DILARANG menyebut laba, margin, atau menu paling menguntungkan.
           Ganti dengan analisis berbasis omzet dan jumlah terjual, lalu tegaskan bahwa mengisi resep &
           harga bahan adalah langkah pertama agar keuntungan bisa dihitung.
        2. Bila hari berpenjualan kurang dari 7, sebut terang-terangan bahwa datanya terlalu sedikit untuk
           prediksi yang andal, dan sampaikan temuannya sebagai indikasi awal, bukan ramalan.
        3. Rekomendasi stok hanya boleh dari field 'pemakaian' (rata_rata_per_hari & perkiraan_habis_hari).
           Bila kosong, katakan pemakaian bahan belum bisa dihitung karena resep belum diisi.
        4. Jangan mengulang seluruh tabel. Sebut angka hanya bila memperkuat kesimpulan.

        FORMAT (pakai judul markdown ##, tanpa judul lain di luar daftar ini):
        ## Ringkasan
        ## Menu Unggulan
        ## Yang Perlu Diperhatikan
        ## Rekomendasi Stok
        ## Kesimpulan
        TXT;

        $isi = "Data periode {$data['periode']['label']}:\n\n"
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($pertanyaanTambahan) {
            $isi .= "\n\nPermintaan khusus dari pemilik: " . mb_substr($pertanyaanTambahan, 0, 500);
        }

        $r = $this->ai->chat(
            [
                ['role' => 'system', 'content' => $sistem],
                ['role' => 'user', 'content' => $isi],
            ],
            null,
            config('ai.groq.model_narasi'),
            (int) config('ai.groq.max_tokens_narasi'),
        );

        if (! $r['ok']) {
            return ['ok' => false, 'teks' => null, 'error' => $r['error'], 'tokens' => 0];
        }

        return [
            'ok' => true,
            'teks' => trim((string) ($r['message']['content'] ?? '')),
            'error' => null,
            'tokens' => $r['tokens_in'] + $r['tokens_out'],
        ];
    }

    private function ya(bool $v): string
    {
        return $v ? 'YA' : 'TIDAK';
    }

    /**
     * Tafsirkan rentang tanggal dari kalimat bebas pengguna.
     *
     * Dikerjakan model karena pengguna menulis bebas ("dari awal Juli sampai
     * kemarin"), tapi hasilnya DIVALIDASI di sini — model cukup sering
     * mengembalikan tanggal yang tidak masuk akal.
     *
     * @return array{dari:string,sampai:string,sumber:string}
     */
    public function tafsirTanggal(?string $kalimat): array
    {
        $bulanIni = [
            'dari' => Carbon::today()->startOfMonth()->toDateString(),
            'sampai' => Carbon::today()->toDateString(),
            'sumber' => 'default (bulan ini)',
        ];

        if (! $kalimat || ! $this->ai->siap()) {
            return $bulanIni;
        }

        $hariIni = Carbon::today()->toDateString();
        $r = $this->ai->chat([
            ['role' => 'system', 'content' =>
                "Hari ini {$hariIni}. Dari kalimat pengguna, tentukan rentang tanggal yang dimaksud. "
                . 'Jawab HANYA JSON: {"dari":"YYYY-MM-DD","sampai":"YYYY-MM-DD"}. '
                . 'Bila tidak ada petunjuk tanggal sama sekali, pakai bulan berjalan.'],
            ['role' => 'user', 'content' => mb_substr($kalimat, 0, 300)],
        ], null, config('ai.groq.model_ringan'));

        if (! $r['ok']) {
            return $bulanIni;
        }

        // Model sering membungkus JSON dengan penjelasan; ambil objek pertamanya.
        if (! preg_match('/\{.*?\}/s', (string) ($r['message']['content'] ?? ''), $m)) {
            return $bulanIni;
        }
        $j = json_decode($m[0], true);
        if (! is_array($j)) {
            return $bulanIni;
        }

        try {
            $dari = Carbon::parse($j['dari'] ?? '')->startOfDay();
            $sampai = Carbon::parse($j['sampai'] ?? '')->startOfDay();
        } catch (\Throwable) {
            return $bulanIni;
        }

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        // Tolak tanggal di luar akal: masa depan jauh atau sebelum aplikasi ada.
        if ($sampai->gt(Carbon::today()->addDay()) || $dari->lt(Carbon::create(2020, 1, 1))) {
            return $bulanIni;
        }
        if ($dari->diffInDays($sampai) > 400) {
            $dari = $sampai->copy()->subDays(400);
        }

        return ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'sumber' => 'dari kalimat Anda'];
    }
}
