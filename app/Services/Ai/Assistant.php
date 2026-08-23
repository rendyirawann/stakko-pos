<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Otak percakapan AI Assistant.
 *
 * Alurnya: pertanyaan → model memilih fungsi database → hasilnya dikembalikan ke
 * model → model menyusun jawaban. Berulang sampai model berhenti memanggil
 * fungsi, dengan batas putaran agar model yang bingung tidak menghabiskan kuota.
 *
 * Yang TIDAK dilakukan di sini: menghitung angka. Semua angka datang dari
 * PostgreSQL lewat DataTools. Model hanya menyusun kalimat dari angka itu.
 */
class Assistant
{
    private DataTools $data;
    private WebSearch $web;
    private GroqClient $ai;

    public function __construct(private Tenant $tenant, private string $userId)
    {
        $this->data = new DataTools((int) $tenant->id);
        $this->web  = new WebSearch();
        $this->ai   = new GroqClient();
    }

    public function siap(): bool
    {
        return $this->ai->siap();
    }

    /**
     * Jawab satu pertanyaan dalam sebuah percakapan.
     *
     * @return array{ok:bool,jawaban:?string,error:?string,sumber:array,brain:string,ms:int}
     */
    public function tanya(AiConversation $obrolan, string $pertanyaan): array
    {
        $mulai = microtime(true);

        if (! $this->ai->siap()) {
            return $this->balas(false, null, 'Fitur AI belum diaktifkan di server ini (GROQ_API_KEY belum diisi).', [], 'none', $mulai);
        }
        if ($lebih = $this->lewatKuota()) {
            return $this->balas(false, null, $lebih, [], 'none', $mulai);
        }

        // Simpan pertanyaan lebih dulu supaya riwayat tetap utuh walau AI gagal.
        AiMessage::create([
            'conversation_id' => $obrolan->id,
            'tenant_id' => $this->tenant->id,
            'role' => 'user',
            'content' => $pertanyaan,
        ]);

        $pesan = array_merge(
            [['role' => 'system', 'content' => $this->promptSistem()]],
            $this->riwayat($obrolan),
            [['role' => 'user', 'content' => $pertanyaan]],
        );

        $alat = array_merge(DataTools::skema(), WebSearch::skema());
        $sumber = [];
        $brain = 'none';
        $tIn = 0;
        $tOut = 0;

        for ($putaran = 0; $putaran < (int) config('ai.max_tool_rounds'); $putaran++) {
            $r = $this->ai->chat($pesan, $alat);
            $tIn += $r['tokens_in'];
            $tOut += $r['tokens_out'];

            if (! $r['ok']) {
                return $this->balas(false, null, $r['error'], $sumber, $brain, $mulai, $obrolan, $tIn, $tOut);
            }

            $m = $r['message'] ?? [];
            $panggilan = $m['tool_calls'] ?? [];

            // Tidak ada fungsi dipanggil lagi → ini jawaban akhirnya.
            if (! $panggilan) {
                $jawaban = trim((string) ($m['content'] ?? ''));
                if ($jawaban === '') {
                    $jawaban = 'Maaf, saya belum bisa menyusun jawaban untuk pertanyaan itu. Coba tanyakan dengan lebih spesifik.';
                }
                $this->simpanJawaban($obrolan, $jawaban, $sumber, $brain, $tIn, $tOut, $mulai);
                return $this->balas(true, $jawaban, null, $sumber, $brain, $mulai);
            }

            // Jejak asisten harus masuk riwayat SEBELUM hasil fungsi, kalau tidak
            // model akan menerima hasil untuk panggilan yang tak pernah ia buat.
            $pesan[] = $m;

            foreach ($panggilan as $p) {
                $nama = $p['function']['name'] ?? '';
                $arg = json_decode($p['function']['arguments'] ?? '{}', true) ?: [];

                if ($nama === 'cari_web') {
                    $hasil = $this->web->cari((string) ($arg['kueri'] ?? ''));
                    if (! empty($hasil['hasil'])) {
                        $brain = $brain === 'database' ? 'database+web' : 'web';
                        foreach ($hasil['hasil'] as $h) {
                            $sumber[] = ['jenis' => 'web', 'judul' => $h['judul'], 'url' => $h['sumber']];
                        }
                    }
                } else {
                    $hasil = $this->data->jalankan($nama, $arg);
                    if (! isset($hasil['error'])) {
                        $brain = $brain === 'web' ? 'database+web' : 'database';
                        $sumber[] = ['jenis' => 'database', 'fungsi' => $nama, 'parameter' => $arg];
                    }
                }

                $pesan[] = [
                    'role' => 'tool',
                    'tool_call_id' => $p['id'] ?? '',
                    'content' => json_encode($hasil, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        // Batas putaran tercapai: lebih baik mengaku daripada menyodorkan jawaban
        // separuh matang yang angkanya belum lengkap.
        $pesan_gagal = 'Pertanyaan ini terlalu banyak langkah untuk dijawab sekaligus. Coba pecah menjadi pertanyaan yang lebih spesifik.';
        $this->catatPemakaian($tIn, $tOut);

        return $this->balas(false, null, $pesan_gagal, $sumber, $brain, $mulai);
    }

    // ==================================================================

    private function promptSistem(): string
    {
        $hariIni = Carbon::today();

        return <<<TXT
        Anda asisten analitik untuk aplikasi kasir "{$this->tenant->name}". Jawab dalam Bahasa Indonesia yang ringkas dan langsung.

        HARI INI: {$hariIni->translatedFormat('l, d F Y')} ({$hariIni->toDateString()}).
        Gunakan tanggal ini untuk menafsirkan "hari ini", "bulan ini", "minggu lalu", dan sejenisnya.

        ATURAN YANG TIDAK BOLEH DILANGGAR:
        1. JANGAN PERNAH mengarang angka. Setiap angka harus berasal dari hasil fungsi. Bila fungsi tidak memberi angkanya, katakan datanya tidak tersedia.
        2. Bila hasil fungsi berisi field 'catatan' atau peringatan, PATUHI isinya dan sampaikan ke pengguna.
        3. Bila 'hpp_tercatat' bernilai false, JANGAN menyebut kata margin, laba, atau keuntungan per menu. Jelaskan bahwa resep/harga bahan belum diisi sehingga laba belum bisa dihitung.
        4. Hasil kosong BUKAN kegagalan. "Tidak ada stok yang menipis" adalah jawaban yang benar dan kabar baik — sampaikan begitu, jangan mencari ke internet.
        5. Gunakan cari_web HANYA untuk pengetahuan umum di luar data toko. Untuk apa pun tentang penjualan, stok, menu, dan HPP toko ini, pakai fungsi database.
        6. Anda hanya bisa MEMBACA. Bila diminta mengubah, menghapus, atau menambah data, tolak dan arahkan ke menu yang bersangkutan.

        GAYA JAWABAN:
        - Sebut angka dalam format rupiah Indonesia (contoh: Rp 1.250.000).
        - Untuk daftar lebih dari 3 baris, pakai tabel markdown.
        - Tutup dengan satu saran praktis bila memang ada yang layak disarankan. Jangan memaksakan saran bila datanya tipis.
        TXT;
    }

    /** Riwayat singkat sebagai konteks; hanya tanya-jawab, hasil fungsi tidak diulang. */
    private function riwayat(AiConversation $obrolan): array
    {
        return AiMessage::where('conversation_id', $obrolan->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit((int) config('ai.konteks_pesan'))
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => (string) $m->content])
            ->values()
            ->all();
    }

    private function simpanJawaban(AiConversation $o, string $jawaban, array $sumber, string $brain, int $tIn, int $tOut, float $mulai): void
    {
        AiMessage::create([
            'conversation_id' => $o->id,
            'tenant_id' => $this->tenant->id,
            'role' => 'assistant',
            'content' => $jawaban,
            'sources' => $sumber,
            'brain' => $brain,
            'tokens_in' => $tIn,
            'tokens_out' => $tOut,
            'ms' => (int) ((microtime(true) - $mulai) * 1000),
        ]);

        $o->forceFill(['last_message_at' => now()])->save();
        $this->catatPemakaian($tIn, $tOut);
    }

    /**
     * Dua pagar kuota harian: per tenant, dan untuk seluruh akun.
     *
     * Pagar global bukan berlebihan — batas token Groq berlaku untuk SATU akun
     * yang dipakai semua tenant. Tanpa pagar itu, tenant yang ramai akan
     * memakan kuota tenant lain, dan yang lain menerima pesan "kuota penuh"
     * tanpa pernah memakai apa pun.
     */
    private function lewatKuota(): ?string
    {
        $perTenant = (int) config('ai.batas.pesan_per_hari_per_tenant');
        if ($perTenant > 0) {
            $pakai = (int) DB::table('ai_usage_daily')
                ->where('tenant_id', $this->tenant->id)
                ->whereDate('day', today())
                ->value('messages');

            if ($pakai >= $perTenant) {
                return "Kuota AI harian toko ini sudah tercapai ({$perTenant} pertanyaan). Silakan lanjut besok.";
            }
        }

        $global = (int) config('ai.batas.pesan_per_hari_global');
        if ($global > 0) {
            $semua = (int) DB::table('ai_usage_daily')
                ->whereDate('day', today())
                ->sum('messages');

            if ($semua >= $global) {
                return 'Kuota AI harian layanan sudah tercapai. Silakan coba lagi besok.';
            }
        }

        return null;
    }

    private function catatPemakaian(int $tIn, int $tOut): void
    {
        // Satu perintah atomik: menyisipkan bila baris harian belum ada, dan
        // MENAMBAH bila sudah. Dipisah menjadi insert-lalu-update akan salah
        // hitung (baris baru langsung jadi 2) dan bisa balapan antar permintaan.
        DB::statement(
            'INSERT INTO ai_usage_daily (tenant_id, day, messages, tokens_in, tokens_out, created_at, updated_at)
             VALUES (?, CURRENT_DATE, 1, ?, ?, now(), now())
             ON CONFLICT (tenant_id, day) DO UPDATE SET
                 messages   = ai_usage_daily.messages + 1,
                 tokens_in  = ai_usage_daily.tokens_in + EXCLUDED.tokens_in,
                 tokens_out = ai_usage_daily.tokens_out + EXCLUDED.tokens_out,
                 updated_at = now()',
            [$this->tenant->id, $tIn, $tOut]
        );
    }

    private function balas(bool $ok, ?string $jawaban, ?string $error, array $sumber, string $brain, float $mulai, ?AiConversation $o = null, int $tIn = 0, int $tOut = 0): array
    {
        if ($o && ! $ok) {
            $this->catatPemakaian($tIn, $tOut);
        }

        return [
            'ok' => $ok,
            'jawaban' => $jawaban,
            'error' => $error,
            'sumber' => $sumber,
            'brain' => $brain,
            'ms' => (int) ((microtime(true) - $mulai) * 1000),
        ];
    }
}
