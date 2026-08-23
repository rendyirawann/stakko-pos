<?php

namespace App\Services\Ai;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PRIMARY BRAIN — jendela AI ke database tenant.
 *
 * Aturan yang tidak boleh dilanggar: **AI tidak pernah menulis SQL.** Ia hanya
 * memilih salah satu metode di kelas ini dan mengisi parameternya (tanggal,
 * nama menu, batas baris). `tenant_id` diambil dari sesi login lewat constructor
 * dan tidak pernah berasal dari model.
 *
 * Alasannya bukan teoretis: database `stakko_pos` menyimpan data 14 tenant dan
 * dipakai bersama dengan aplikasi mobile. Satu query tanpa filter tenant sudah
 * cukup untuk membocorkan penjualan satu toko ke toko lain.
 *
 * Semua angka dihitung PostgreSQL, bukan oleh model bahasa. Rumusnya disamakan
 * dengan SalesReportController supaya AI dan halaman laporan tidak pernah
 * menyebut dua angka berbeda untuk hal yang sama:
 *   omzet = Σ grand_total  (payment_status='paid' DAN voided_at IS NULL)
 *   hpp   = Σ order_details.hpp untuk pesanan tsb
 *   laba  = omzet − hpp − beban
 */
class DataTools
{
    public function __construct(private int $tenantId) {}

    // ==================================================================
    //  Definisi skema fungsi untuk dikirim ke model (format OpenAI/Groq)
    // ==================================================================

    /**
     * Daftar fungsi yang boleh dipanggil AI, beserta parameternya.
     *
     * Deskripsinya ditulis untuk dibaca MODEL, bukan manusia: kalimatnya
     * menegaskan kapan sebuah fungsi dipakai, karena kesalahan tersering adalah
     * model memilih fungsi yang salah lalu menyimpulkan hal yang keliru.
     */
    public static function skema(): array
    {
        $tanggal = [
            'dari'   => ['type' => 'string', 'description' => 'Tanggal awal, format YYYY-MM-DD'],
            'sampai' => ['type' => 'string', 'description' => 'Tanggal akhir, format YYYY-MM-DD'],
        ];

        $f = fn (string $nama, string $ket, array $props = [], array $wajib = []) => [
            'type' => 'function',
            'function' => [
                'name' => $nama,
                'description' => $ket,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $props ?: new \stdClass(),
                    'required' => $wajib,
                ],
            ],
        ];

        return [
            $f('ringkasan_toko',
               'Gambaran umum toko saat ini: jumlah menu, bahan, pesanan hari ini, dan omzet hari ini. Pakai ini bila pertanyaannya umum seperti "bagaimana kondisi toko?".'),

            $f('stok_bahan',
               'Daftar SEMUA bahan beserta sisa stok dan batas minimumnya. Pakai bila ditanya stok bahan secara umum.'),

            $f('stok_menipis',
               'Bahan yang sisa stoknya SUDAH di bawah batas minimum atau habis. Pakai untuk pertanyaan "stok apa yang mau habis / perlu dibeli".'),

            $f('menu_tidak_bisa_dijual',
               'Menu yang tidak bisa dijual sekarang: ditandai tidak tersedia, atau ada bahan resepnya yang stoknya habis. Pakai untuk pertanyaan "menu apa yang kosong / tidak bisa dijual".'),

            $f('hpp_menu',
               'HPP (harga pokok), harga jual, dan margin untuk satu atau beberapa menu berdasarkan resepnya. Pakai bila ditanya HPP/margin/untung per menu.',
               ['nama_menu' => ['type' => 'string', 'description' => 'Nama menu atau sebagiannya. Kosongkan untuk mengambil semua menu yang punya resep.']]),

            $f('laba_rugi',
               'Omzet, HPP, beban, dan laba bersih untuk satu rentang tanggal. Ini sumber angka keuangan — jangan pernah menghitung laba sendiri dari fungsi lain.',
               $tanggal, ['dari', 'sampai']),

            $f('menu_terlaris',
               'Peringkat menu dalam rentang tanggal: jumlah terjual, omzet, HPP, dan laba per menu. Pakai untuk "menu paling laris", "menu paling menguntungkan", atau "menu yang rugi".',
               $tanggal + [
                   'urut' => ['type' => 'string', 'enum' => ['qty', 'omzet', 'laba'], 'description' => 'Dasar pengurutan. Default: qty'],
                   'batas' => ['type' => 'integer', 'description' => 'Jumlah baris, maksimal 30. Default 10'],
               ], ['dari', 'sampai']),

            $f('penjualan_harian',
               'Omzet, jumlah nota, HPP, dan laba PER HARI dalam rentang tanggal. Pakai untuk melihat tren, hari tersibuk, atau bahan prediksi.',
               $tanggal, ['dari', 'sampai']),

            $f('beban_per_kategori',
               'Rincian pengeluaran per kategori dalam rentang tanggal.',
               $tanggal, ['dari', 'sampai']),

            $f('pemakaian_bahan',
               'Total pemakaian setiap bahan dalam rentang tanggal, berdasarkan penjualan yang benar-benar terjadi. Ini dasar untuk memperkirakan stok yang perlu ditambah.',
               $tanggal, ['dari', 'sampai']),
        ];
    }

    /** Jalankan fungsi yang dipilih AI. Nama tak dikenal ditolak, bukan diterka. */
    public function jalankan(string $nama, array $arg): array
    {
        return match ($nama) {
            'ringkasan_toko'          => $this->ringkasanToko(),
            'stok_bahan'              => $this->stokBahan(),
            'stok_menipis'            => $this->stokMenipis(),
            'menu_tidak_bisa_dijual'  => $this->menuTidakBisaDijual(),
            'hpp_menu'                => $this->hppMenu($arg['nama_menu'] ?? null),
            'laba_rugi'               => $this->labaRugi(...$this->rentang($arg)),
            'menu_terlaris'           => $this->menuTerlaris(...[...$this->rentang($arg), $arg['urut'] ?? 'qty', (int) ($arg['batas'] ?? 10)]),
            'penjualan_harian'        => $this->penjualanHarian(...$this->rentang($arg)),
            'beban_per_kategori'      => $this->bebanPerKategori(...$this->rentang($arg)),
            'pemakaian_bahan'         => $this->pemakaianBahan(...$this->rentang($arg)),
            default                   => ['error' => "Fungsi '{$nama}' tidak tersedia."],
        };
    }

    // ==================================================================
    //  Pembantu
    // ==================================================================

    /**
     * Validasi rentang tanggal dari AI.
     *
     * Model sering mengarang tanggal ("2024-13-45") atau menukar urutannya.
     * Dibersihkan di sini supaya query tidak pernah menerima nilai liar, dan
     * rentang dibatasi 400 hari agar satu pertanyaan tidak memindai bertahun
     * data lalu membanjiri konteks model.
     */
    private function rentang(array $arg): array
    {
        $dari   = $this->tanggal($arg['dari'] ?? null)   ?: Carbon::today()->startOfMonth();
        $sampai = $this->tanggal($arg['sampai'] ?? null) ?: Carbon::today();

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        if ($dari->diffInDays($sampai) > 400) {
            $dari = $sampai->copy()->subDays(400);
        }

        return [$dari->toDateString(), $sampai->toDateString()];
    }

    private function tanggal(?string $v): ?Carbon
    {
        if (! $v) {
            return null;
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Query dasar pesanan yang DIHITUNG: sudah dibayar dan bukan pesanan salah. */
    private function pesananSah(string $dari, string $sampai)
    {
        return DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('payment_status', 'paid')
            ->whereNull('voided_at')
            ->whereRaw('created_at::date BETWEEN ?::date AND ?::date', [$dari, $sampai]);
    }

    // ==================================================================
    //  Fungsi data
    // ==================================================================

    private function ringkasanToko(): array
    {
        $hariIni = Carbon::today()->toDateString();
        $hari = $this->pesananSah($hariIni, $hariIni)
            ->selectRaw('COUNT(*) AS nota, COALESCE(SUM(grand_total),0) AS omzet')->first();

        return [
            'tanggal_hari_ini'   => $hariIni,
            'jumlah_menu'        => (int) DB::table('menus')->where('tenant_id', $this->tenantId)->count(),
            'menu_tersedia'      => (int) DB::table('menus')->where('tenant_id', $this->tenantId)->where('is_available', true)->count(),
            'jumlah_bahan'       => (int) DB::table('ingredients')->where('tenant_id', $this->tenantId)->count(),
            'nota_hari_ini'      => (int) ($hari->nota ?? 0),
            'omzet_hari_ini'     => (float) ($hari->omzet ?? 0),
            'jumlah_menu_beresep' => (int) DB::table('menu_ingredients')->where('tenant_id', $this->tenantId)->distinct()->count('menu_id'),
        ];
    }

    private function stokBahan(): array
    {
        $rows = DB::table('ingredients as i')
            ->leftJoin('ingredient_batches as b', function ($j) {
                $j->on('b.ingredient_id', '=', 'i.id')->where('b.tenant_id', '=', $this->tenantId);
            })
            ->where('i.tenant_id', $this->tenantId)
            ->groupBy('i.id', 'i.name', 'i.unit', 'i.minimum_stock')
            ->orderBy('i.name')
            ->selectRaw('i.name, i.unit, i.minimum_stock,
                         COALESCE(SUM(b.remaining_quantity),0) AS sisa,
                         MIN(b.expiry_date) FILTER (WHERE b.remaining_quantity > 0) AS expired_terdekat')
            ->get();

        return [
            'jumlah' => $rows->count(),
            'bahan'  => $rows->map(fn ($r) => [
                'nama'             => $r->name,
                'satuan'           => $r->unit,
                'sisa_stok'        => (float) $r->sisa,
                'batas_minimum'    => (float) $r->minimum_stock,
                'status'           => $this->statusStok((float) $r->sisa, (float) $r->minimum_stock),
                'expired_terdekat' => $r->expired_terdekat,
            ])->all(),
        ];
    }

    private function statusStok(float $sisa, float $min): string
    {
        if ($sisa <= 0) return 'habis';
        if ($min > 0 && $sisa <= $min) return 'menipis';
        return 'aman';
    }

    private function stokMenipis(): array
    {
        $semua = $this->stokBahan()['bahan'];
        $kritis = array_values(array_filter($semua, fn ($b) => $b['status'] !== 'aman'));

        return [
            'jumlah' => count($kritis),
            // Dinyatakan tegas supaya model tidak menafsirkan daftar kosong
            // sebagai "data tidak ditemukan" lalu mencari-cari ke web.
            'catatan' => count($kritis) === 0
                ? 'Tidak ada bahan yang menipis. Semua stok di atas batas minimum.'
                : 'Bahan berikut perlu dibeli.',
            'bahan' => $kritis,
        ];
    }

    private function menuTidakBisaDijual(): array
    {
        // Menu ditandai tidak tersedia oleh kasir/pemilik.
        $ditandai = DB::table('menus')
            ->where('tenant_id', $this->tenantId)->where('is_available', false)
            ->orderBy('name')->pluck('name')->all();

        // Menu yang resepnya memuat bahan berstok habis: tetap ditandai
        // tersedia, tapi kenyataannya tidak bisa dimasak.
        $habis = DB::table('menus as m')
            ->join('menu_ingredients as mi', function ($j) {
                $j->on('mi.menu_id', '=', 'm.id')->where('mi.tenant_id', '=', $this->tenantId);
            })
            ->join('ingredients as i', 'i.id', '=', 'mi.ingredient_id')
            ->leftJoin('ingredient_batches as b', function ($j) {
                $j->on('b.ingredient_id', '=', 'i.id')->where('b.tenant_id', '=', $this->tenantId);
            })
            ->where('m.tenant_id', $this->tenantId)
            ->where('m.is_available', true)
            ->groupBy('m.id', 'm.name', 'i.name', 'mi.quantity')
            ->havingRaw('COALESCE(SUM(b.remaining_quantity),0) < mi.quantity')
            ->selectRaw('m.name AS menu, i.name AS bahan, mi.quantity AS butuh, COALESCE(SUM(b.remaining_quantity),0) AS sisa')
            ->get();

        return [
            'ditandai_tidak_tersedia' => $ditandai,
            'kehabisan_bahan' => $habis->map(fn ($r) => [
                'menu'   => $r->menu,
                'bahan_kurang' => $r->bahan,
                'dibutuhkan'   => (float) $r->butuh,
                'sisa_stok'    => (float) $r->sisa,
            ])->all(),
            'catatan' => ($ditandai === [] && $habis->isEmpty())
                ? 'Semua menu bisa dijual. Tidak ada yang kosong.'
                : null,
        ];
    }

    private function hppMenu(?string $nama): array
    {
        // HPP = Σ (kebutuhan bahan × harga beli rata-rata bahan itu).
        // Harga rata-rata dipakai, bukan harga batch terakhir, agar angkanya
        // tidak melompat setiap kali ada pembelian dengan harga berbeda.
        $q = DB::table('menus as m')
            ->join('menu_ingredients as mi', function ($j) {
                $j->on('mi.menu_id', '=', 'm.id')->where('mi.tenant_id', '=', $this->tenantId);
            })
            ->join('ingredients as i', 'i.id', '=', 'mi.ingredient_id')
            ->leftJoin(DB::raw('(
                SELECT ingredient_id,
                       CASE WHEN SUM(initial_quantity) > 0
                            THEN SUM(buy_price_total) / SUM(initial_quantity) ELSE 0 END AS harga_rata
                FROM ingredient_batches WHERE tenant_id = ' . (int) $this->tenantId . '
                GROUP BY ingredient_id
            ) hb'), 'hb.ingredient_id', '=', 'i.id')
            ->where('m.tenant_id', $this->tenantId);

        if ($nama) {
            $q->where('m.name', 'ilike', '%' . $nama . '%');
        }

        $rows = $q->groupBy('m.id', 'm.name', 'm.price')
            ->orderBy('m.name')
            ->limit(40)
            ->selectRaw('m.name, m.price, COALESCE(SUM(mi.quantity * COALESCE(hb.harga_rata,0)),0) AS hpp')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'jumlah' => 0,
                'catatan' => $nama
                    ? "Tidak ada menu bernama '{$nama}' yang punya resep. HPP hanya bisa dihitung untuk menu yang resepnya sudah diisi."
                    : 'Belum ada menu yang resepnya diisi, jadi HPP belum bisa dihitung.',
                'menu' => [],
            ];
        }

        return [
            'jumlah' => $rows->count(),
            'menu' => $rows->map(function ($r) {
                $harga = (float) $r->price;
                $hpp   = round((float) $r->hpp, 2);
                return [
                    'nama'       => $r->name,
                    'harga_jual' => $harga,
                    'hpp'        => $hpp,
                    'laba_kotor' => round($harga - $hpp, 2),
                    'margin_persen' => $harga > 0 ? round(($harga - $hpp) / $harga * 100, 1) : null,
                ];
            })->all(),
        ];
    }

    private function labaRugi(string $dari, string $sampai): array
    {
        $o = $this->pesananSah($dari, $sampai)
            ->selectRaw('COUNT(*) AS nota, COALESCE(SUM(grand_total),0) AS omzet,
                         COALESCE(SUM(discount_amount),0) AS diskon')->first();

        $idPesanan = $this->pesananSah($dari, $sampai)->select('id');
        $hpp = (float) DB::table('order_details')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('order_id', $idPesanan)
            ->sum('hpp');

        $beban = (float) DB::table('expenses')
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('date', [$dari, $sampai])
            ->sum('amount');

        $omzet = (float) ($o->omzet ?? 0);

        return [
            'periode'      => "{$dari} s/d {$sampai}",
            'jumlah_nota'  => (int) ($o->nota ?? 0),
            'omzet'        => $omzet,
            'total_diskon' => (float) ($o->diskon ?? 0),
            'hpp'          => round($hpp, 2),
            'laba_kotor'   => round($omzet - $hpp, 2),
            'beban'        => $beban,
            'laba_bersih'  => round($omzet - $hpp - $beban, 2),
            'margin_bersih_persen' => $omzet > 0 ? round(($omzet - $hpp - $beban) / $omzet * 100, 1) : null,
            'hpp_tercatat' => $hpp > 0,
            // Peringatan ini penting: HPP 0 BUKAN berarti untung penuh, melainkan
            // resep/harga bahan belum diisi. Tanpa penegasan ini model akan
            // menyimpulkan "margin 100%" dan memberi saran bisnis yang salah.
            'catatan' => $omzet == 0
                ? 'Tidak ada penjualan yang tercatat pada periode ini.'
                : ($hpp == 0
                    ? 'PERHATIAN: HPP belum tercatat (Rp 0) karena resep menu atau harga beli bahan belum diisi. Karena itu laba_kotor di sini SAMA DENGAN omzet dan BUKAN laba sebenarnya. Jangan menyebutnya margin atau keuntungan; sampaikan bahwa HPP perlu diisi dulu agar laba bisa dihitung.'
                    : 'Pesanan yang dibatalkan (void) tidak dihitung.'),
        ];
    }

    private function menuTerlaris(string $dari, string $sampai, string $urut, int $batas): array
    {
        $kolom = match ($urut) {
            'omzet' => 'omzet',
            'laba'  => 'laba',
            default => 'qty',
        };
        $batas = max(1, min(30, $batas));

        $rows = DB::table('order_details as d')
            ->join('orders as o', 'o.id', '=', 'd.order_id')
            ->leftJoin('menus as m', 'm.id', '=', 'd.menu_id')
            ->where('d.tenant_id', $this->tenantId)
            ->where('o.payment_status', 'paid')
            ->whereNull('o.voided_at')
            ->whereRaw('o.created_at::date BETWEEN ?::date AND ?::date', [$dari, $sampai])
            ->groupBy('d.menu_id', 'm.name')
            ->orderByDesc($kolom)
            ->limit($batas)
            ->selectRaw('COALESCE(m.name, \'(menu dihapus)\') AS nama,
                         SUM(d.qty) AS qty,
                         SUM(d.subtotal) AS omzet,
                         SUM(d.hpp) AS hpp,
                         SUM(d.subtotal) - SUM(d.hpp) AS laba')
            ->get();

        $adaHpp = $rows->sum(fn ($r) => (float) $r->hpp) > 0;

        return [
            'periode' => "{$dari} s/d {$sampai}",
            'diurut_berdasarkan' => $kolom,
            'jumlah' => $rows->count(),
            'hpp_tercatat' => $adaHpp,
            'catatan' => $rows->isEmpty()
                ? 'Tidak ada penjualan pada periode ini.'
                : (! $adaHpp
                    ? 'PERHATIAN: HPP semua menu masih Rp 0 karena resep belum diisi, jadi kolom laba di sini sebenarnya OMZET, bukan laba. Jangan menyebut margin atau keuntungan per menu; urutkan berdasarkan omzet/terjual saja dan sarankan mengisi resep.'
                    : null),
            'menu' => $rows->map(function ($r) use ($adaHpp) {
                $omzet = (float) $r->omzet;
                $laba  = (float) $r->laba;
                return [
                    'nama'  => $r->nama,
                    'terjual' => (int) $r->qty,
                    'omzet' => $omzet,
                    'hpp'   => round((float) $r->hpp, 2),
                    // Bila HPP belum tercatat, 'laba' & margin sengaja dikosongkan
                    // supaya model tidak bisa melaporkan margin 100% yang palsu.
                    'laba'  => $adaHpp ? round($laba, 2) : null,
                    'margin_persen' => ($adaHpp && $omzet > 0) ? round($laba / $omzet * 100, 1) : null,
                ];
            })->all(),
        ];
    }

    private function penjualanHarian(string $dari, string $sampai): array
    {
        $rows = DB::table('orders as o')
            ->leftJoin('order_details as d', 'd.order_id', '=', 'o.id')
            ->where('o.tenant_id', $this->tenantId)
            ->where('o.payment_status', 'paid')
            ->whereNull('o.voided_at')
            ->whereRaw('o.created_at::date BETWEEN ?::date AND ?::date', [$dari, $sampai])
            ->groupByRaw('o.created_at::date')
            ->orderByRaw('o.created_at::date')
            ->selectRaw('o.created_at::date AS hari,
                         COUNT(DISTINCT o.id) AS nota,
                         COALESCE(SUM(d.subtotal),0) AS omzet_item,
                         COALESCE(SUM(d.hpp),0) AS hpp')
            ->get();

        $adaHpp = $rows->sum(fn ($r) => (float) $r->hpp) > 0;

        return [
            'periode' => "{$dari} s/d {$sampai}",
            'jumlah_hari_ada_penjualan' => $rows->count(),
            'catatan' => $rows->isEmpty() ? 'Tidak ada penjualan pada periode ini.' : null,
            'hpp_tercatat' => $adaHpp,
            'harian' => $rows->map(function ($r) use ($adaHpp) {
                return [
                    'tanggal' => (string) $r->hari,
                    'nota'    => (int) $r->nota,
                    'omzet'   => (float) $r->omzet_item,
                    'hpp'     => round((float) $r->hpp, 2),
                    'laba_kotor' => $adaHpp ? round((float) $r->omzet_item - (float) $r->hpp, 2) : null,
                ];
            })->all(),
        ];
    }

    private function bebanPerKategori(string $dari, string $sampai): array
    {
        $rows = DB::table('expenses')
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('date', [$dari, $sampai])
            ->groupBy('category')
            ->orderByDesc('total')
            ->selectRaw('COALESCE(category, \'(tanpa kategori)\') AS kategori, SUM(amount) AS total, COUNT(*) AS jumlah')
            ->get();

        return [
            'periode' => "{$dari} s/d {$sampai}",
            'total'   => round((float) $rows->sum('total'), 2),
            'catatan' => $rows->isEmpty() ? 'Tidak ada pengeluaran tercatat pada periode ini.' : null,
            'kategori' => $rows->map(fn ($r) => [
                'kategori' => $r->kategori,
                'total'    => (float) $r->total,
                'jumlah_transaksi' => (int) $r->jumlah,
            ])->all(),
        ];
    }

    private function pemakaianBahan(string $dari, string $sampai): array
    {
        // Dihitung dari resep × jumlah menu terjual, bukan dari stock_movements,
        // supaya tetap terisi meski pencatatan mutasi stok belum lengkap.
        $rows = DB::table('order_details as d')
            ->join('orders as o', 'o.id', '=', 'd.order_id')
            ->join('menu_ingredients as mi', function ($j) {
                $j->on('mi.menu_id', '=', 'd.menu_id')->where('mi.tenant_id', '=', $this->tenantId);
            })
            ->join('ingredients as i', 'i.id', '=', 'mi.ingredient_id')
            ->where('d.tenant_id', $this->tenantId)
            ->where('o.payment_status', 'paid')
            ->whereNull('o.voided_at')
            ->whereRaw('o.created_at::date BETWEEN ?::date AND ?::date', [$dari, $sampai])
            ->groupBy('i.id', 'i.name', 'i.unit')
            ->orderByDesc('terpakai')
            ->limit(30)
            ->selectRaw('i.name, i.unit, SUM(d.qty * mi.quantity) AS terpakai')
            ->get();

        $hari = max(1, Carbon::parse($dari)->diffInDays(Carbon::parse($sampai)) + 1);
        $sisa = collect($this->stokBahan()['bahan'])->keyBy('nama');

        return [
            'periode' => "{$dari} s/d {$sampai}",
            'jumlah_hari' => $hari,
            'catatan' => $rows->isEmpty()
                ? 'Tidak ada pemakaian bahan tercatat. Kemungkinan resep menu belum diisi atau belum ada penjualan.'
                : 'rata_rata_per_hari dan perkiraan_habis_hari dihitung dari pemakaian pada periode ini.',
            'bahan' => $rows->map(function ($r) use ($hari, $sisa) {
                $perHari = (float) $r->terpakai / $hari;
                $stok = (float) ($sisa[$r->name]['sisa_stok'] ?? 0);
                return [
                    'nama'   => $r->name,
                    'satuan' => $r->unit,
                    'total_terpakai' => round((float) $r->terpakai, 3),
                    'rata_rata_per_hari' => round($perHari, 3),
                    'sisa_stok' => $stok,
                    'perkiraan_habis_hari' => $perHari > 0 ? round($stok / $perHari, 1) : null,
                ];
            })->all(),
        ];
    }
}
