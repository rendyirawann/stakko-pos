<?php

/**
 * Konfigurasi fitur AI (AI Assistant & AI Prediksi).
 *
 * Penyedia: Groq (https://console.groq.com) — API-nya kompatibel format OpenAI,
 * jadi klien di app/Services/Ai/GroqClient.php bisa dipindah ke penyedia lain
 * tanpa menulis ulang bila suatu saat batas gratisnya tidak lagi memadai.
 */
return [

    'groq' => [
        'key'   => env('GROQ_API_KEY'),
        'base'  => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),

        /**
         * Model utama — dipakai AI Assistant (tool calling).
         *
         * WAJIB mendukung tool calling; tanpa itu AI tidak bisa membaca database
         * dan hanya akan mengarang angka. Sudah diuji pada akun ini:
         *   openai/gpt-oss-120b  OK  ~1.011 token/putaran
         *   openai/gpt-oss-20b   OK
         *   qwen/qwen3.6-27b     OK  ~2.523 token/putaran (model reasoning)
         *   groq/compound*       TIDAK mendukung tool calling
         *   meta-llama/llama-prompt-guard-*  BUKAN model chat (konteks 512)
         *
         * gpt-oss-120b dipilih karena batas akun hanya 8.000 token/menit —
         * qwen memakai 2,5x lebih banyak per putaran sehingga satu pertanyaan
         * bisa menghabiskan kuota satu menit.
         */
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),

        // Model ringan untuk tugas remeh (judul percakapan, tafsir tanggal).
        'model_ringan' => env('GROQ_MODEL_LIGHT', 'openai/gpt-oss-20b'),

        /**
         * Model untuk NARASI laporan (AI Prediksi) — satu panggilan, tanpa tool
         * calling, jadi biaya per putaran tidak jadi penghambat. Di sini prosa
         * qwen sedikit lebih kaya, dan frekuensinya rendah.
         *
         * Model reasoning menulis proses berpikirnya di dalam <think>...</think>
         * dan itu ikut terkirim; GroqClient::bersihkanPenalaran() membuangnya.
         * Jatah tokennya HARUS lebih besar: dengan 2048, qwen menghabiskan
         * seluruhnya untuk berpikir dan jawabannya tidak pernah keluar.
         */
        'model_narasi'      => env('GROQ_MODEL_NARASI', 'qwen/qwen3.6-27b'),
        'max_tokens_narasi' => (int) env('GROQ_MAX_TOKENS_NARASI', 4096),

        'timeout'     => (int) env('GROQ_TIMEOUT', 60),
        'temperature' => (float) env('GROQ_TEMPERATURE', 0.2),  // rendah: ini soal angka, bukan karangan
        'max_tokens'  => (int) env('GROQ_MAX_TOKENS', 2048),
    ],

    /**
     * Batas putaran tool calling dalam satu pertanyaan. Tanpa batas, model yang
     * bingung bisa memanggil fungsi berulang tanpa henti dan menghabiskan kuota.
     */
    'max_tool_rounds' => (int) env('AI_MAX_TOOL_ROUNDS', 6),

    /**
     * "Second brain" — pencarian web untuk pertanyaan pengetahuan umum yang
     * memang TIDAK ada di database (mis. "cara menekan food cost").
     * Dimatikan bila kunci kosong; AI akan berkata tidak tahu, bukan mengarang.
     */
    'search' => [
        'provider' => env('AI_SEARCH_PROVIDER', 'serper'),   // serper | brave | none
        'key'      => env('AI_SEARCH_KEY'),
        'max'      => (int) env('AI_SEARCH_MAX', 5),
        'timeout'  => (int) env('AI_SEARCH_TIMEOUT', 15),
    ],

    /**
     * Pagar pemakaian per tenant per hari. Groq free tier dibatasi per menit dan
     * per hari untuk SELURUH akun — tanpa pagar ini, satu tenant yang aktif bisa
     * menghabiskan kuota semua tenant lain di jam sibuk.
     */
    'batas' => [
        /**
         * Angka ini diturunkan dari batas NYATA akun (diukur 2026-08-21):
         * 8.000 token/menit dan 200.000 token/hari per model, dan satu
         * pertanyaan bertool-calling memakan ~2.000 token. Artinya kapasitas
         * seluruh akun hanya sekitar 100 pertanyaan/hari — untuk SEMUA tenant,
         * bukan per tenant.
         *
         * Karena itu batas per tenant harus jauh di bawah kapasitas akun, dan
         * ada pagar global: tanpa itu satu toko yang ramai di jam makan siang
         * bisa menghabiskan kuota 13 tenant lain sebelum tengah hari.
         */
        'pesan_per_hari_per_tenant' => (int) env('AI_DAILY_LIMIT_TENANT', 20),
        'pesan_per_hari_global'     => (int) env('AI_DAILY_LIMIT_GLOBAL', 80),
        'pesan_per_menit_per_user'  => (int) env('AI_MINUTE_LIMIT_USER', 4),
    ],

    // Riwayat percakapan yang dikirim ulang sebagai konteks (pasangan tanya-jawab).
    'konteks_pesan' => (int) env('AI_CONTEXT_MESSAGES', 8),
];
