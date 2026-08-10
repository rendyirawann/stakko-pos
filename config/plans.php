<?php

/**
 * Definisi paket langganan SaaS.
 * - basic      : paket entry, checkout via Midtrans.
 * - enterprise : paket lanjutan (manajemen meja, HPP, laporan keuangan), checkout via Midtrans.
 * - customize  : paket fleksibel (kontrak 2 tahun) via konsultasi WhatsApp (diaktifkan manual Superadmin).
 *
 * Catatan: "Starter" di landing = akun DEPOSIT (pay-as-you-go), bukan plan bulanan di sini.
 *
 * FITUR TAMBAHAN (ADD-ON): modul juga bisa dibeli satuan tanpa menaikkan paket,
 * lewat tabel `tenant_addons` (lihat App\Tenancy\Addon dan perintah
 * `php artisan tenant:addon`). Add-on boleh dibatasi ke peran tertentu — fiturnya
 * tetap bekerja untuk seluruh transaksi toko, hanya LAYARNYA yang dibatasi.
 *
 * "modules" = daftar fitur yang boleh diakses paket tsb. Modul yang dikenal:
 * kasir, kitchen, report_sales, promo, report_items, data_master, resources, expense,
 * tables (manajemen meja), hpp (menu HPP), report_finance (laporan keuangan),
 * qr_selforder (QR self-order), payment_gateway (setelan payment gateway).
 */
// ===== Paket per VERTICAL. F&B tetap di key 'plans' agar kode lama tak berubah. =====
$fnbPlans = [
        'basic' => [
            'name'  => 'Basic',
            'price' => 199000,
            'periods' => [
                ['months' => 1,  'price_per_month' => 199000, 'label' => 'Bulanan'],
                ['months' => 3,  'price_per_month' => 169000, 'label' => 'Promo 3 Bulan'],
                ['months' => 6,  'price_per_month' => 149000, 'label' => 'Promo 6 Bulan'],
                ['months' => 12, 'price_per_month' => 129000, 'label' => 'Promo 12 Bulan'],
            ],
            'tagline' => 'Semua yang dibutuhkan untuk mulai jualan dengan rapi & cepat.',
            'limits' => ['outlets' => 1, 'staff' => 3, 'customers' => 12000],
            'modules' => ['kasir', 'kitchen', 'report_sales', 'data_master', 'resources', 'expense'],
            'features' => [
                'Kasir / POS satu layar (Tunai & QRIS)',
                'Kitchen Display (layar dapur)',
                'Add-on menu & nomor antrian di struk',
                'Laporan penjualan',
                'Data master menu & kategori',
                'Maks 3 User (tambah user Rp10.000/user)',
                'Penyimpanan Database Pelanggan (12.000 Data)',
            ],
        ],

        'enterprise' => [
            'name'  => 'Enterprise',
            'price' => 399000,
            'periods' => [
                ['months' => 1,  'price_per_month' => 399000, 'label' => 'Bulanan'],
                ['months' => 6,  'price_per_month' => 349000, 'label' => 'Promo 6 Bulan'],
                ['months' => 12, 'price_per_month' => 329000, 'label' => 'Promo 12 Bulan'],
            ],
            'tagline' => 'Untuk bisnis berkembang dengan manajemen yang lebih lengkap.',
            'limits' => ['outlets' => 1, 'staff' => 5, 'customers' => 50000],
            'modules' => [
                'kasir', 'kitchen', 'report_sales', 'data_master', 'resources', 'expense',
                'promo', 'report_items', 'tables', 'hpp', 'report_finance',
            ],
            'features' => [
                'Semua fitur paket Basic',
                'Manajemen Pengaturan Meja',
                'Menu HPP',
                'Laporan Keuangan',
                'Maks 5 User (tambah user Rp10.000/user)',
                'Penyimpanan Database Pelanggan (50.000 Data)',
            ],
        ],

        'customize' => [
            'name'  => 'Customize',
            'price' => 0,
            'contact' => true,     // konsultasi WhatsApp, bukan checkout Midtrans
            'wa' => '6285760366666',
            'tagline' => 'Rakit paketmu sendiri — kontrak 2 tahun, fitur menyesuaikan bisnis.',
            'limits' => ['outlets' => null, 'staff' => null, 'customers' => null],
            'modules' => [
                'kasir', 'kitchen', 'report_sales', 'data_master', 'resources', 'expense',
                'promo', 'report_items', 'tables', 'hpp', 'report_finance',
                'qr_selforder', 'payment_gateway',
                // Modul HPP + Inventory (FIFO/FEFO) + Resep: KHUSUS paket Customize.
                'inventory_hpp',
                // Split bill & merge table (kasir lanjutan): KHUSUS paket Customize.
                'split_merge',
            ],
            'features' => [
                'Semua fitur Enterprise & Basic',
                'HPP, Inventory (FIFO/FEFO) & Resep — modal bahan & food cost akurat',
                'Split bill & merge table (gabung/pecah nota per meja)',
                'Tanpa batasan jumlah user',
                'Penyimpanan Database Pelanggan (Tidak Terbatas)',
                'VPS & domain sendiri',
                'QR Menu & Self Order pelanggan',
                'Payment Gateway + Setting Payment',
                'Tambah fitur/menu khusus (maks 3; lebih kena charge)',
                'Konsultasi & support prioritas',
            ],
        ],
];

/**
 * Paket LAUNDRY (vertical 'laundry'). Modul khas: laundry_service, laundry_produksi.
 * Harga mengacu Mooda-Laundry-Proposal.pdf; bisa diubah Superadmin di Setelan Paket.
 */
$laundryPlans = [
    'basic' => [
        'name'  => 'Basic',
        'price' => 149000,
        'periods' => [
            ['months' => 1,  'price_per_month' => 149000, 'label' => 'Bulanan'],
            ['months' => 3,  'price_per_month' => 129000, 'label' => 'Promo 3 Bulan'],
            ['months' => 6,  'price_per_month' => 119000, 'label' => 'Promo 6 Bulan'],
            ['months' => 12, 'price_per_month' => 99000,  'label' => 'Promo 12 Bulan'],
        ],
        'tagline' => 'Untuk laundry rumahan / kiloan skala kecil.',
        'limits' => ['outlets' => 1, 'staff' => 3, 'customers' => 5000],
        'modules' => ['kasir', 'laundry_service', 'laundry_produksi', 'report_sales', 'data_master', 'resources', 'expense'],
        'features' => [
            'Kasir & nota laundry (kiloan, satuan, express)',
            'Manajemen layanan & harga',
            'Alur status cucian (Diterima → Diambil)',
            'Database pelanggan + riwayat',
            'Nota / struk thermal + kode rak',
            'Laporan penjualan',
            'Maks 3 User (tambah user Rp10.000/user)',
            'Penyimpanan Database Pelanggan (5.000 Data)',
        ],
    ],

    'pro' => [
        'name'  => 'Pro',
        'price' => 299000,
        'periods' => [
            ['months' => 1,  'price_per_month' => 299000, 'label' => 'Bulanan'],
            ['months' => 3,  'price_per_month' => 269000, 'label' => 'Promo 3 Bulan'],
            ['months' => 6,  'price_per_month' => 239000, 'label' => 'Promo 6 Bulan'],
            ['months' => 12, 'price_per_month' => 199000, 'label' => 'Promo 12 Bulan'],
        ],
        'tagline' => 'Laundry berkembang, banyak layanan + antar-jemput.',
        'limits' => ['outlets' => 1, 'staff' => 5, 'customers' => 25000],
        'modules' => [
            'kasir', 'laundry_service', 'laundry_produksi', 'report_sales', 'data_master',
            'resources', 'expense', 'promo', 'report_items', 'report_finance',
        ],
        'features' => [
            'Semua fitur Basic',
            'Member & paket langganan pelanggan',
            'Antar-jemput (pickup & delivery)',
            'Laporan keuangan & laba per layanan',
            'Multi-kasir / multi-shift',
            'Maks 5 User (tambah user Rp10.000/user)',
            'Penyimpanan Database Pelanggan (25.000 Data)',
        ],
    ],

    'bisnis' => [
        'name'  => 'Bisnis',
        'price' => 499000,
        'periods' => [
            ['months' => 1,  'price_per_month' => 499000, 'label' => 'Bulanan'],
            ['months' => 3,  'price_per_month' => 449000, 'label' => 'Promo 3 Bulan'],
            ['months' => 6,  'price_per_month' => 399000, 'label' => 'Promo 6 Bulan'],
            ['months' => 12, 'price_per_month' => 349000, 'label' => 'Promo 12 Bulan'],
        ],
        'tagline' => 'Untuk jaringan / multi-cabang laundry.',
        'limits' => ['outlets' => null, 'staff' => 15, 'customers' => 100000],
        'modules' => [
            'kasir', 'laundry_service', 'laundry_produksi', 'report_sales', 'data_master',
            'resources', 'expense', 'promo', 'report_items', 'report_finance', 'hpp', 'payment_gateway',
        ],
        'features' => [
            'Semua fitur Pro',
            'Multi-outlet (banyak cabang, satu dashboard)',
            'Manajemen karyawan, shift & komisi',
            'HPP & laba per layanan / cabang',
            'Payment gateway (QRIS / Virtual Account)',
            'QR / barcode per pesanan (tracking rak)',
            'Maks 15 User (tambah user Rp10.000/user)',
            'Penyimpanan Database Pelanggan (100.000 Data)',
        ],
    ],

    'customize' => [
        'name'  => 'Customize',
        'price' => 0,
        'contact' => true,
        'wa' => '6285760366666',
        'tagline' => 'Rakit paketmu sendiri — untuk waralaba / kebutuhan khusus.',
        'limits' => ['outlets' => null, 'staff' => null, 'customers' => null],
        'modules' => [
            'kasir', 'laundry_service', 'laundry_produksi', 'report_sales', 'data_master',
            'resources', 'expense', 'promo', 'report_items', 'report_finance', 'hpp',
            'qr_selforder', 'payment_gateway',
        ],
        'features' => [
            'Semua fitur Bisnis',
            'Tanpa batasan jumlah user',
            'Database pelanggan tidak terbatas',
            'VPS & domain sendiri / white-label',
            'API / integrasi sistem lain',
            'Konsultasi & support prioritas',
        ],
    ],
];

return [

    'currency' => 'Rp',
    'trial_days' => 14,

    // Biaya tambah user di luar kuota paket.
    'extra_user_price' => 10000,

    // Default (F&B) — dipakai kode lama yang belum vertical-aware.
    'plans' => $fnbPlans,

    // Paket per vertical. Plan::all($vertical) membaca dari sini.
    'verticals' => [
        'fnb'     => $fnbPlans,
        'laundry' => $laundryPlans,
    ],
];
