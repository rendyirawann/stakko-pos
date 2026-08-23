<?php

/**
 * KATALOG FITUR TAMBAHAN (ADD-ON).
 *
 * Sumber tunggal untuk: daftar yang bisa dipilih tenant di halaman Langganan,
 * harga per bulan, dan peran default yang boleh membuka layarnya.
 *
 * Modul di sini SENGAJA tidak dimasukkan ke config/plans.php. Sebagian
 * (khususnya AI) memakan kuota berbiaya setiap kali dipakai, jadi harus
 * diaktifkan sadar per tenant — bukan ikut menyala begitu paket dinaikkan.
 *
 * `peran_default` mengisi `allowed_roles`: kosong berarti semua peran yang
 * berhak menurut izin biasa. Untuk modul yang menampilkan angka laba, defaultnya
 * dibatasi ke pemilik — kasir tidak perlu melihat margin toko.
 */
return [

    'inventory_hpp' => [
        'label'         => 'HPP & Inventory',
        'harga'         => 10000,
        'ikon'          => 'ki-chart-pie-simple',
        'warna'         => 'warning',
        'ringkas'       => 'Hitung HPP per menu dari resep, kelola stok bahan dengan FIFO/FEFO.',
        'peran_default' => ['owner'],
        'vertical'      => ['fnb'],
    ],

    'ai_assistant' => [
        'label'         => 'AI Assistant',
        'harga'         => 25000,
        'ikon'          => 'ki-messages',
        'warna'         => 'success',
        'ringkas'       => 'Tanya jawab tentang stok, HPP, dan laba toko Anda. Jawaban diambil langsung dari database, bukan perkiraan.',
        'catatan'       => 'Dibatasi ' . (int) env('AI_DAILY_LIMIT_TENANT', 20) . ' pertanyaan per hari.',
        'peran_default' => ['owner'],
        'vertical'      => ['fnb', 'laundry'],
    ],

    'ai_prediksi' => [
        'label'         => 'AI Prediksi',
        'harga'         => 35000,
        'ikon'          => 'ki-chart-line-up',
        'warna'         => 'primary',
        'ringkas'       => 'Analisis penjualan per periode, perkiraan stok yang perlu ditambah, dan laporan PDF siap cetak.',
        'peran_default' => ['owner'],
        'vertical'      => ['fnb', 'laundry'],
    ],

];
