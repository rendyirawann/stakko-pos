<?php

/**
 * Konfigurasi khusus Mooda.
 */
return [
    // URL unduhan APK eksternal (opsional). Jika kosong & file lokal ada di
    // public/downloads/mooda-pos.apk, sistem memakai file lokal tsb.
    'mobile_apk_url' => env('MOBILE_APK_URL'),

    // Versi aplikasi tablet yang ditampilkan di halaman unduhan.
    'mobile_version' => env('MOBILE_APK_VERSION', '1.0.2'),

    // versionCode APK TERBARU yang tersedia. APK dengan versionCode lebih kecil (dibaca dari
    // User-Agent "MoodaAPK/<code>") akan diberi popup "update tersedia" di halaman login.
    // Naikkan nilai ini (atau set env APK_LATEST_VERSION_CODE) SETIAP rilis APK baru,
    // seiring menaikkan versionCode di mobile/android-webview/app/build.gradle.
    'apk_latest_version_code' => (int) env('APK_LATEST_VERSION_CODE', 3),

    // Nomor WhatsApp support (dipakai di beberapa halaman).
    'support_wa' => env('SUPPORT_WA', '6285760366666'),

    // URL sosial media untuk footer landing. Set di .env; default '#' (belum diarahkan).
    'social_instagram' => env('SOCIAL_INSTAGRAM', '#'),
    'social_facebook'  => env('SOCIAL_FACEBOOK', '#'),
    'social_youtube'   => env('SOCIAL_YOUTUBE', '#'),
    'social_tiktok'    => env('SOCIAL_TIKTOK', '#'),
];
