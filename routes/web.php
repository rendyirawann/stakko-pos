<?php

use Illuminate\Support\Facades\Route;

// Dashboard
use App\Http\Controllers\Backend\Dashboard\DashboardAdminController;
// Profile
use App\Http\Controllers\Backend\MyProfile\AccountController;
use App\Http\Controllers\Backend\MyProfile\ProfileController;
use App\Http\Controllers\Backend\MyProfile\SecurityController;
use App\Http\Controllers\Backend\MyProfile\ActivityController;
use App\Http\Controllers\Backend\MyProfile\LoginSessionController;
// User Management
use App\Http\Controllers\Backend\UserManagement\UserController;
use App\Http\Controllers\Backend\UserManagement\RoleController;
// Help / Log
use App\Http\Controllers\Backend\Help\LogActivityController;
// POS
use App\Http\Controllers\Backend\Kasir\KasirController;
use App\Http\Controllers\Backend\Kasir\ShiftController;
use App\Http\Controllers\Backend\Kitchen\KitchenController;
// Data Master
use App\Http\Controllers\Backend\Master\CategoriesController;
use App\Http\Controllers\Backend\Master\MenuController;
use App\Http\Controllers\Backend\Master\PromoController;
use App\Http\Controllers\Backend\Master\DiningTableController;
// Reports
use App\Http\Controllers\Backend\Report\ItemSalesReportController;
use App\Http\Controllers\Backend\Report\SalesReportController;
use App\Http\Controllers\Backend\Finance\ExpenseController;
// Settings / Billing / Tenant
use App\Http\Controllers\Backend\SettingController;
use App\Http\Controllers\Backend\DownloadAppController;
use App\Http\Controllers\Backend\Billing\BillingController;
use App\Http\Controllers\Backend\Billing\CheckoutController;
use App\Http\Controllers\Backend\Billing\DepositController;
use App\Http\Controllers\Backend\Superadmin\TenantController;
use App\Http\Controllers\Backend\Superadmin\DemoAccountController;
use App\Http\Controllers\Backend\Laundry\LaundryServiceController;
use App\Http\Controllers\Backend\Laundry\LaundryCustomerController;
use App\Http\Controllers\Backend\Laundry\LaundryKasirController;
use App\Http\Controllers\Backend\Laundry\LaundryProduksiController;
use App\Http\Controllers\Backend\Superadmin\DepositSettingController;
use App\Http\Controllers\Backend\Superadmin\DokuChannelController;
use App\Http\Controllers\Backend\Superadmin\PaymentGatewayController;
use App\Http\Controllers\Backend\Superadmin\TripayChannelController;
use App\Http\Controllers\Backend\Superadmin\PartnerLogoController;
use App\Http\Controllers\Backend\Superadmin\SiteContentController;
use App\Http\Controllers\Backend\Superadmin\FaqController;
use App\Http\Controllers\Backend\Superadmin\SocialLinkController;
use App\Http\Controllers\Backend\Superadmin\MaintenanceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ===== Subdomain: Blog (blog.mooda.id) & Affiliate (affiliate.mooda.id) =====
// Dilayani app yang sama via Octane. Didaftarkan SEBELUM route '/' landing agar
// request ke host subdomain diprioritaskan; mooda.id sendiri tetap ke landing.
Route::domain('blog.mooda.id')->group(base_path('routes/blog.php'));
Route::domain('affiliate.mooda.id')->group(base_path('routes/affiliate.php'));

// Modul BLOG — ADMIN (host utama, /admin/blog*). File route terpisah, khusus
// Superadmin (can:blog.manage). Bukan fitur tenant -> TANPA 'subscribed'.
Route::middleware(['auth', 'forbid-banned-user', 'can:blog.manage'])
    ->group(base_path('routes/blog_admin.php'));

// Modul AFFILIATE — ADMIN (host utama, /admin/affiliates*). Khusus Superadmin.
Route::middleware(['auth', 'forbid-banned-user', 'can:affiliate.manage'])
    ->group(base_path('routes/affiliate_admin.php'));

// Program Affiliate untuk OWNER tenant (gabung + dashboard di dalam POS mooda.id/admin).
Route::middleware(['auth', 'forbid-banned-user', 'can:affiliate.refer'])->group(function () {
    Route::get('/admin/affiliate-saya', [\App\Http\Controllers\Backend\Affiliate\MyAffiliateController::class, 'index'])->name('affiliate.my');
    Route::post('/admin/affiliate-saya/join', [\App\Http\Controllers\Backend\Affiliate\MyAffiliateController::class, 'join'])->name('affiliate.my.join');
});

// Halaman Depan: Landing Page SaaS.
// Vertical NON-F&B (mis. laundry.mooda.id) TIDAK punya landing page -> langsung ke login.
Route::get('/', function () {
    $vertical = \App\Verticals\VerticalRegistry::current();
    if ($vertical !== 'fnb') {
        return redirect()->route('login');
    }

    return view('landing', [
        'partnerLogos' => \App\Models\PartnerLogo::forLanding(),
        'faqs'         => \App\Models\Faq::activeOrdered(),
        'socialLinks'  => \App\Models\SocialLink::activeOrdered(),
    ]);
})->name('landing');

// Halaman "Tentang Kami" (profil perusahaan) — dibuka di tab baru dari navbar.
Route::get('/tentang-kami', function () {
    return view('tentang-kami', [
        'founders' => \App\Models\Founder::orderBy('sort_order')->orderBy('id')->get(),
    ]);
})->name('tentang');

// Halaman checkout (contoh publik) — memperlihatkan alur bayar Mooda (VA/QRIS) untuk
// verifikasi merchant pembayaran. Checkout sebenarnya ada di /admin/deposit (perlu login).
Route::get('/checkout-demo', fn () => view('checkout-demo'))->name('checkout-demo');

// SEO: sitemap.xml (dinamis — domain mengikuti APP_URL). Dirujuk di robots.txt.
Route::get('/sitemap.xml', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
        . '  <url>' . "\n"
        . '    <loc>' . e(url('/')) . '</loc>' . "\n"
        . '    <lastmod>' . now()->toDateString() . '</lastmod>' . "\n"
        . '    <changefreq>weekly</changefreq>' . "\n"
        . '    <priority>1.0</priority>' . "\n"
        . '  </url>' . "\n"
        . '</urlset>';
    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');

// Group Middleware untuk User yang sudah Login (+ blokir user yang di-ban + mode pemeliharaan
// + wajib email terverifikasi/aktif; akun lama sudah di-grandfather via migration)
Route::middleware(['auth', 'forbid-banned-user', 'maintenance', 'verified'])->group(function () {

    // --- DASHBOARD (accessible by ALL authenticated roles) ---
    Route::get('/admin/dashboard', [DashboardAdminController::class, 'index'])->name('dashboard');
    // Tampil/sembunyi panduan "Setup Awal" di dashboard (preferensi per-tenant)
    Route::post('/admin/dashboard/onboarding-toggle', [DashboardAdminController::class, 'toggleOnboarding'])->name('dashboard.onboarding-toggle');
    // Rincian HPP per menu (modal dashboard) — mengikuti filter bulan.
    Route::get('/admin/dashboard/hpp-breakdown', [DashboardAdminController::class, 'hppBreakdown'])->name('dashboard.hpp-breakdown');
    // Toggle mode tampilan Superadmin: analytics (platform) <-> pos (kasir)
    Route::get('/admin/view-mode/{mode}', [DashboardAdminController::class, 'switchMode'])->name('view-mode.switch');
    // Superadmin memilih toko yang dioperasikan di mode POS
    Route::get('/admin/pos-tenant/{id}', [DashboardAdminController::class, 'setPosTenant'])->name('pos-tenant.set');

    // --- MY ACCOUNT / PROFILE (accessible by ALL authenticated users) ---
    Route::get('/admin/my-account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/admin/my-account/{id}/avatar', [AccountController::class, 'editAvatar'])->name('avatar-edit');
    Route::post('/admin/my-account/{id}/update-avatar', [AccountController::class, 'updateAvatar'])->name('avatar-update');

    Route::resource('/admin/my-profile', ProfileController::class);
    Route::resource('/admin/my-security', SecurityController::class);
    Route::post('/admin/my-security', [SecurityController::class, 'store'])->name('change.password');

    Route::get('/admin/my-activity', [ActivityController::class, 'index'])->name('my-activity.index');
    Route::get('/admin/mget-my-activity', [ActivityController::class, 'getActivity'])->name('get-my-activity');

    Route::get('/admin/mmy-login-session', [LoginSessionController::class, 'index'])->name('my-login-session.index');
    Route::get('/admin/mget-my-login-session', [LoginSessionController::class, 'getLoginSession'])->name('get-my-login-session');

    // --- SETTINGS (accessible by ALL authenticated users) ---
    Route::get('/admin/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/admin/settings/update', [SettingController::class, 'update'])->name('settings.update');

    // --- APLIKASI TABLET (APK) — hanya tenant berlangganan aktif ---
    Route::middleware('subscribed')->group(function () {
        Route::get('/admin/download-app', [DownloadAppController::class, 'index'])->name('download-app');
        Route::get('/admin/download-app/apk', [DownloadAppController::class, 'apk'])->name('download-app.apk');
    });

    // ====================================================
    // BILLING / LANGGANAN — Owner & admin tenant (TANPA 'subscribed' agar bisa bayar saat belum aktif)
    // ====================================================
    Route::middleware('can:view_billing')->group(function () {
        Route::get('/admin/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/admin/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');

        // Halaman checkout terpadu (ringkasan plan/deposit + kartu metode + bayar in-app)
        Route::get('/admin/checkout', [CheckoutController::class, 'show'])->name('checkout.show');

        // Plan Deposit / Poin
        Route::get('/admin/deposit', [DepositController::class, 'index'])->name('deposit.index');
        Route::post('/admin/deposit/checkout', [DepositController::class, 'checkout'])->name('deposit.checkout');
        Route::post('/admin/deposit/switch', [DepositController::class, 'switchToDeposit'])->name('deposit.switch');
    });

    // ====================================================
    // MODUL LAUNDRY (vertical 'laundry') — hanya tenant laundry (superadmin bebas)
    // ====================================================
    Route::middleware('vertical:laundry')->group(function () {
        // Data master: layanan & pelanggan
        Route::middleware('can:view_data_master')->group(function () {
            Route::get('/admin/laundry/services', [LaundryServiceController::class, 'index'])->name('laundry.services.index');
            Route::post('/admin/laundry/services', [LaundryServiceController::class, 'store'])->name('laundry.services.store');
            Route::put('/admin/laundry/services/{service}', [LaundryServiceController::class, 'update'])->name('laundry.services.update');
            Route::post('/admin/laundry/services/{service}/toggle', [LaundryServiceController::class, 'toggle'])->name('laundry.services.toggle');
            Route::delete('/admin/laundry/services/{service}', [LaundryServiceController::class, 'destroy'])->name('laundry.services.destroy');

            Route::get('/admin/laundry/customers', [LaundryCustomerController::class, 'index'])->name('laundry.customers.index');
            Route::post('/admin/laundry/customers', [LaundryCustomerController::class, 'store'])->name('laundry.customers.store');
            Route::put('/admin/laundry/customers/{customer}', [LaundryCustomerController::class, 'update'])->name('laundry.customers.update');
            Route::delete('/admin/laundry/customers/{customer}', [LaundryCustomerController::class, 'destroy'])->name('laundry.customers.destroy');
        });

        // Kasir laundry (POS)
        Route::middleware('can:view_kasir')->group(function () {
            Route::get('/admin/laundry/kasir', [LaundryKasirController::class, 'index'])->name('laundry.kasir.index');
            Route::get('/admin/laundry/kasir/create', [LaundryKasirController::class, 'create'])->name('laundry.kasir.create');
            Route::post('/admin/laundry/kasir', [LaundryKasirController::class, 'store'])->name('laundry.kasir.store');
            // Sinkron nota yang dibuat saat OFFLINE (idempoten via client_txn_id).
            Route::post('/admin/laundry/kasir/sync-offline', [LaundryKasirController::class, 'syncOffline'])->name('laundry.kasir.sync-offline');
            Route::post('/admin/laundry/kasir/{order}/pay', [LaundryKasirController::class, 'pay'])->name('laundry.kasir.pay');
            Route::post('/admin/laundry/kasir/{order}/handover', [LaundryKasirController::class, 'handover'])->name('laundry.kasir.handover');
            Route::get('/admin/laundry/kasir/{order}/print', [LaundryKasirController::class, 'print'])->name('laundry.kasir.print');
        });

        // Produksi (workshop board)
        Route::middleware('can:view_kitchen')->group(function () {
            Route::get('/admin/laundry/produksi', [LaundryProduksiController::class, 'index'])->name('laundry.produksi.index');
            Route::post('/admin/laundry/produksi/{order}/advance', [LaundryProduksiController::class, 'advance'])->name('laundry.produksi.advance');
        });
    });

    // ====================================================
    // MANAJEMEN SHIFT — Superadmin saja (koreksi modal & uang aktual)
    // Tidak memakai 'can:view_tenants' saja: ini menyentuh angka uang, jadi
    // dikunci tegas ke peran Superadmin.
    // ====================================================
    Route::middleware('role:Superadmin')->group(function () {
        // Kelola fitur tambahan semua tenant (pengajuan -> aktifkan).
        Route::get('/admin/superadmin/addons', [\App\Http\Controllers\Backend\Superadmin\AddonController::class, 'index'])->name('superadmin.addons.index');
        Route::post('/admin/superadmin/addons/beri', [\App\Http\Controllers\Backend\Superadmin\AddonController::class, 'beri'])->name('superadmin.addons.beri');
        Route::post('/admin/superadmin/addons/{id}/aktifkan', [\App\Http\Controllers\Backend\Superadmin\AddonController::class, 'aktifkan'])->name('superadmin.addons.aktifkan');
        Route::post('/admin/superadmin/addons/{id}/batalkan', [\App\Http\Controllers\Backend\Superadmin\AddonController::class, 'batalkan'])->name('superadmin.addons.batalkan');

        Route::get('/admin/superadmin/shifts', [\App\Http\Controllers\Backend\Superadmin\ShiftManagementController::class, 'index'])
            ->name('superadmin.shifts.index');
        Route::put('/admin/superadmin/shifts/{id}', [\App\Http\Controllers\Backend\Superadmin\ShiftManagementController::class, 'update'])
            ->name('superadmin.shifts.update');
    });

    // ====================================================
    // MANAJEMEN TENANT — Superadmin (lintas tenant)
    // ====================================================
    Route::middleware('can:view_tenants')->group(function () {
        Route::get('/admin/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/admin/tenants/data', [TenantController::class, 'getData'])->name('tenants.data');
        // create HARUS sebelum {id} agar "create" tak dianggap id.
        Route::get('/admin/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/admin/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/admin/tenants/{id}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
        Route::post('/admin/tenants/{id}/update', [TenantController::class, 'update'])->name('tenants.update');
        Route::post('/admin/tenants/{id}/users', [TenantController::class, 'storeUser'])->name('tenants.users.store');
        Route::get('/admin/tenants/{id}', [TenantController::class, 'show'])->name('tenants.show');
        Route::post('/admin/tenants/{id}/toggle-active', [TenantController::class, 'toggleActive'])->name('tenants.toggle-active');
        Route::post('/admin/tenants/{id}/reset-data', [TenantController::class, 'resetData'])->name('tenants.reset-data');
        Route::post('/admin/tenants/{id}/subscription', [TenantController::class, 'updateSubscription'])->name('tenants.subscription.update');
        Route::delete('/admin/tenants/{id}', [TenantController::class, 'destroy'])->name('tenants.destroy');

        // Akun Demo (generate tenant + owner + kasir + saldo Rp5.000; deposit Rp5.000 ke akun) — Superadmin
        Route::get('/admin/demo-accounts', [DemoAccountController::class, 'index'])->name('demo-accounts.index');
        Route::post('/admin/demo-accounts/generate', [DemoAccountController::class, 'generate'])->name('demo-accounts.generate');
        Route::post('/admin/demo-accounts/deposit', [DemoAccountController::class, 'deposit'])->name('demo-accounts.deposit');

        // Platform Menu — semua menu Superadmin dalam grid kartu (anti menu terpotong di layar kecil)
        Route::get('/admin/platform-menu', [\App\Http\Controllers\Backend\Superadmin\PlatformMenuController::class, 'index'])->name('platform-menu.index');

        // Setelan Paket langganan (harga dasar, diskon %, label promo, toggle) — Superadmin
        Route::get('/admin/plan-settings', [\App\Http\Controllers\Backend\Superadmin\PlanController::class, 'index'])->name('plan-settings.index');
        Route::post('/admin/plan-settings', [\App\Http\Controllers\Backend\Superadmin\PlanController::class, 'save'])->name('plan-settings.save');

        // Tentang Kami / Founder (nama, jabatan, bio, upload foto) — Superadmin
        Route::get('/admin/founders', [\App\Http\Controllers\Backend\Superadmin\FounderController::class, 'index'])->name('founders.index');
        Route::post('/admin/founders', [\App\Http\Controllers\Backend\Superadmin\FounderController::class, 'update'])->name('founders.update');
        Route::post('/admin/founders/{id}/remove-photo', [\App\Http\Controllers\Backend\Superadmin\FounderController::class, 'removePhoto'])->name('founders.remove-photo');

        // Setelan Plan Deposit (platform-wide, Superadmin)
        Route::get('/admin/deposit-settings', [DepositSettingController::class, 'index'])->name('deposit-settings.index');
        Route::post('/admin/deposit-settings', [DepositSettingController::class, 'update'])->name('deposit-settings.update');
        Route::post('/admin/deposit-settings/manual-topup', [DepositSettingController::class, 'manualTopup'])->name('deposit-settings.manual-topup');

        // Channel Virtual Account DOKU (SNAP) — platform-wide, Superadmin
        Route::get('/admin/doku-channels', [DokuChannelController::class, 'index'])->name('doku-channels.index');
        Route::post('/admin/doku-channels', [DokuChannelController::class, 'store'])->name('doku-channels.store');
        Route::put('/admin/doku-channels/{channel}', [DokuChannelController::class, 'update'])->name('doku-channels.update');
        Route::post('/admin/doku-channels/{channel}/toggle', [DokuChannelController::class, 'toggle'])->name('doku-channels.toggle');
        Route::delete('/admin/doku-channels/{channel}', [DokuChannelController::class, 'destroy'])->name('doku-channels.destroy');

        // Payment Gateway aktif (pilih SATU: midtrans / doku / tripay) — platform-wide, Superadmin
        Route::get('/admin/payment-gateway', [PaymentGatewayController::class, 'index'])->name('payment-gateway.index');
        Route::post('/admin/payment-gateway', [PaymentGatewayController::class, 'update'])->name('payment-gateway.update');

        // Channel Pembayaran Tripay (dikelola manual, mirip DOKU) — platform-wide, Superadmin
        Route::get('/admin/tripay-channels', [TripayChannelController::class, 'index'])->name('tripay-channels.index');
        Route::post('/admin/tripay-channels/sync', [TripayChannelController::class, 'sync'])->name('tripay-channels.sync');
        Route::post('/admin/tripay-channels', [TripayChannelController::class, 'store'])->name('tripay-channels.store');
        Route::put('/admin/tripay-channels/{channel}', [TripayChannelController::class, 'update'])->name('tripay-channels.update');
        Route::post('/admin/tripay-channels/{channel}/toggle', [TripayChannelController::class, 'toggle'])->name('tripay-channels.toggle');
        Route::delete('/admin/tripay-channels/{channel}', [TripayChannelController::class, 'destroy'])->name('tripay-channels.destroy');

        // Logo Partner (marquee landing) — platform-wide, Superadmin
        Route::get('/admin/partner-logos', [PartnerLogoController::class, 'index'])->name('partner-logos.index');
        Route::post('/admin/partner-logos', [PartnerLogoController::class, 'store'])->name('partner-logos.store');
        Route::put('/admin/partner-logos/{partnerLogo}', [PartnerLogoController::class, 'update'])->name('partner-logos.update');
        Route::post('/admin/partner-logos/{partnerLogo}/toggle', [PartnerLogoController::class, 'toggle'])->name('partner-logos.toggle');
        Route::delete('/admin/partner-logos/{partnerLogo}', [PartnerLogoController::class, 'destroy'])->name('partner-logos.destroy');
        Route::post('/admin/partner-logos-limit', [PartnerLogoController::class, 'updateLimit'])->name('partner-logos.limit');

        // Kelola Situs (CMS landing per-situs: mooda.id / blog / affiliate) — Superadmin
        Route::get('/admin/kelola-situs', [SiteContentController::class, 'index'])->name('site-content.index');
        Route::post('/admin/kelola-situs/{site}', [SiteContentController::class, 'update'])->name('site-content.update');

        // FAQ / Q&A landing (mooda.id) — Superadmin
        Route::get('/admin/faqs', [FaqController::class, 'index'])->name('faqs.index');
        Route::post('/admin/faqs/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
        Route::post('/admin/faqs', [FaqController::class, 'store'])->name('faqs.store');
        Route::put('/admin/faqs/{faq}', [FaqController::class, 'update'])->name('faqs.update');
        Route::post('/admin/faqs/{faq}/toggle', [FaqController::class, 'toggle'])->name('faqs.toggle');
        Route::delete('/admin/faqs/{faq}', [FaqController::class, 'destroy'])->name('faqs.destroy');

        // Sosial media footer landing — Superadmin (ikon auto dari URL)
        Route::get('/admin/social-links', [SocialLinkController::class, 'index'])->name('social-links.index');
        Route::post('/admin/social-links', [SocialLinkController::class, 'store'])->name('social-links.store');
        Route::put('/admin/social-links/{social}', [SocialLinkController::class, 'update'])->name('social-links.update');
        Route::post('/admin/social-links/{social}/toggle', [SocialLinkController::class, 'toggle'])->name('social-links.toggle');
        Route::delete('/admin/social-links/{social}', [SocialLinkController::class, 'destroy'])->name('social-links.destroy');

        // Mode Pemeliharaan (platform-wide, Superadmin)
        Route::get('/admin/maintenance-settings', [MaintenanceController::class, 'index'])->name('maintenance-settings.index');
        Route::post('/admin/maintenance-settings', [MaintenanceController::class, 'update'])->name('maintenance-settings.update');
    });

    // ====================================================
    // KASIR (POS satu-halaman, tanpa meja) : view_kasir — Superadmin, admin, kasir
    // ====================================================
    Route::middleware(['can:view_kasir', 'subscribed'])->group(function () {
        // Shift — halaman bisa dilihat semua (view_kasir); AKSI dibatasi permission:
        //  - buka/tutup  : kasir & owner (shift.operate)
        //  - buka kembali: owner/admin (shift.reopen)
        Route::get('/admin/shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('/admin/shifts/open', [ShiftController::class, 'openShift'])->middleware('can:shift.operate')->name('shifts.open');
        Route::post('/admin/shifts/close/{id}', [ShiftController::class, 'closeShift'])->middleware('can:shift.operate')->name('shifts.close');
        Route::post('/admin/shifts/reopen/{id}', [ShiftController::class, 'reopenShift'])->middleware('can:shift.reopen')->name('shifts.reopen');
        Route::post('/admin/shifts/{id}/modal', [ShiftController::class, 'updateModal'])->middleware('can:shift.reopen')->name('shifts.update-modal');

        // Kasir single-page — KHUSUS F&B. Tenant laundry memakai /admin/laundry/kasir,
        // jadi kasir F&B (meja, menu, kirim ke dapur) diblokir untuk vertical lain.
        Route::middleware('vertical:fnb')->group(function () {
        Route::get('/admin/kasir', [KasirController::class, 'index'])->name('kasir.index');
        Route::post('/admin/kasir/toggle-tables', [KasirController::class, 'toggleTables'])->name('kasir.toggle-tables');
        Route::get('/admin/kasir/orders', [KasirController::class, 'listOrders'])->name('kasir.orders');
        Route::get('/admin/kasir/order/{id}', [KasirController::class, 'showOrder'])->name('kasir.order.show');
        Route::post('/admin/kasir/order/store', [KasirController::class, 'storeOrder'])->name('kasir.store');
        Route::post('/admin/kasir/order/sync-offline', [KasirController::class, 'syncOfflineOrders'])->name('kasir.sync-offline');
        Route::post('/admin/kasir/order/{id}/pay', [KasirController::class, 'payOrder'])->name('kasir.pay');
        // Tambah/gabung item ke pesanan yang MASIH BELUM LUNAS (view -> tambah menu -> merge).
        Route::post('/admin/kasir/order/{id}/add-items', [KasirController::class, 'addItems'])->name('kasir.add-items');
        Route::post('/admin/kasir/order/{id}/complete', [KasirController::class, 'completeOrder'])->name('kasir.complete');
        // Split bill & Merge table — kasir lanjutan, KHUSUS paket Customize (plan:split_merge).
        Route::middleware('plan:split_merge')->group(function () {
            // Split bill: pecah nota belum lunas jadi 2 (pilih item/qty yang dipindah).
            Route::post('/admin/kasir/order/{id}/split', [KasirController::class, 'splitOrder'])->name('kasir.split');
            // Merge table: gabungkan beberapa nota belum lunas ke satu nota tujuan.
            Route::post('/admin/kasir/orders/merge', [KasirController::class, 'mergeOrders'])->name('kasir.merge');
            // Unmerge: pisahkan nota gabungan kembali ke nota-nota asalnya.
            Route::post('/admin/kasir/order/{id}/unmerge', [KasirController::class, 'unmergeOrder'])->name('kasir.unmerge');
        });
        Route::get('/admin/kasir/print/{id}', [KasirController::class, 'printReceipt'])->name('kasir.print');

        // Aksi sensitif khusus OWNER (Superadmin lolos via Gate::before) —
        // hapus pesanan & reset penjualan hari ini.
        Route::delete('/admin/kasir/order/{id}', [KasirController::class, 'destroyOrder'])
            ->middleware('can:order.delete')->name('kasir.order.destroy');
        // Tandai / batalkan tanda "SALAH" pada pesanan SELESAI (OWNER + KASIR).
        // Toggle: pesanan salah tidak dihitung ke omzet & kas, tetap tampil di laporan.
        Route::post('/admin/kasir/order/{id}/void', [KasirController::class, 'voidOrder'])
            ->middleware('can:order.void')->name('kasir.order.void');
        Route::post('/admin/kasir/sales/reset-today', [KasirController::class, 'resetToday'])
            ->middleware('can:sales.clear')->name('kasir.sales.reset-today');
        }); // /vertical:fnb

        // Target harian: DIPAKAI SEMUA VERTICAL (F&B = target penjualan, laundry = target profit).
        Route::post('/admin/kasir/sales/target', [KasirController::class, 'setTarget'])
            ->middleware('can:sales.target')->name('kasir.sales.target');
    });

    // ====================================================
    // KITCHEN: view_kitchen — Superadmin, admin, kasir, kitchen
    // ====================================================
    Route::middleware(['can:view_kitchen', 'subscribed', 'vertical:fnb'])->group(function () {
        Route::get('/admin/kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
        Route::post('/admin/kitchen/update-status', [KitchenController::class, 'updateItemStatus'])->name('kitchen.update-status');
        Route::post('/admin/kitchen/update-order-status', [KitchenController::class, 'updateOrderStatus'])->name('kitchen.update-order-status');
    });

    // ====================================================
    // ====================================================
    // AI ASSISTANT & AI PREDIKSI — modul add-on, TIDAK termasuk paket mana pun
    // ====================================================
    // Sengaja tidak dimasukkan ke config/plans.php: setiap pertanyaan memakai
    // kuota penyedia AI yang berbiaya, jadi fitur ini harus diaktifkan sadar
    // per tenant lewat `php artisan tenant:addon <tenant> ai_assistant`.
    // Karena modulnya tak ada di paket, `plan:` hanya lolos bila add-on aktif,
    // dan `addon:` membatasi peran mana yang boleh membuka layarnya.
    Route::middleware(['subscribed', 'plan:ai_assistant', 'addon:ai_assistant'])->group(function () {
        Route::get('/admin/ai/assistant', [\App\Http\Controllers\Backend\Ai\AssistantController::class, 'index'])->name('ai.assistant.index');
        Route::get('/admin/ai/assistant/{uuid}', [\App\Http\Controllers\Backend\Ai\AssistantController::class, 'show'])->name('ai.assistant.show');
        Route::post('/admin/ai/assistant/kirim', [\App\Http\Controllers\Backend\Ai\AssistantController::class, 'kirim'])->name('ai.assistant.kirim');
        Route::delete('/admin/ai/assistant/{uuid}', [\App\Http\Controllers\Backend\Ai\AssistantController::class, 'hapus'])->name('ai.assistant.hapus');
    });

    // Pengajuan fitur tambahan oleh tenant. TIDAK digerbangi `plan:` -- justru
    // dipakai oleh tenant yang BELUM punya modulnya.
    Route::post('/admin/billing/addon/ajukan', [\App\Http\Controllers\Backend\Billing\BillingController::class, 'ajukanAddon'])
        ->name('billing.addon.ajukan');

    Route::middleware(['subscribed', 'plan:ai_prediksi', 'addon:ai_prediksi'])->group(function () {
        Route::get('/admin/ai/prediksi', [\App\Http\Controllers\Backend\Ai\PrediksiController::class, 'index'])->name('ai.prediksi.index');
        Route::post('/admin/ai/prediksi/analisis', [\App\Http\Controllers\Backend\Ai\PrediksiController::class, 'analisis'])->name('ai.prediksi.analisis');
        Route::post('/admin/ai/prediksi/tafsir-tanggal', [\App\Http\Controllers\Backend\Ai\PrediksiController::class, 'tafsirTanggal'])->name('ai.prediksi.tafsir');
        Route::get('/admin/ai/prediksi/pdf', [\App\Http\Controllers\Backend\Ai\PrediksiController::class, 'pdf'])->name('ai.prediksi.pdf');
    });

    // DATA MASTER: view_data_master — Superadmin, admin
    // ====================================================
    // ====================================================
    // MODUL HPP · INVENTORY (FIFO/FEFO) · RESEP — F&B, KHUSUS paket Customize
    // Gate: permission data master + langganan aktif + vertical fnb + modul inventory_hpp
    // ====================================================
    // 'addon:inventory_hpp' hanya menggigit bila modulnya datang dari add-on:
    // tenant yang mendapatkannya dari paket tetap memakai aturan izin biasa.
    Route::middleware(['can:view_data_master', 'subscribed', 'vertical:fnb', 'plan:inventory_hpp', 'addon:inventory_hpp'])->group(function () {
        // Bahan baku
        Route::get('/admin/fnb/ingredients', [\App\Http\Controllers\Backend\Fnb\IngredientController::class, 'index'])->name('fnb.ingredients.index');
        Route::post('/admin/fnb/ingredients', [\App\Http\Controllers\Backend\Fnb\IngredientController::class, 'store'])->name('fnb.ingredients.store');
        Route::post('/admin/fnb/ingredients/{ingredient}', [\App\Http\Controllers\Backend\Fnb\IngredientController::class, 'update'])->name('fnb.ingredients.update');
        Route::delete('/admin/fnb/ingredients/{ingredient}', [\App\Http\Controllers\Backend\Fnb\IngredientController::class, 'destroy'])->name('fnb.ingredients.destroy');

        // Supplier
        Route::get('/admin/fnb/suppliers', [\App\Http\Controllers\Backend\Fnb\SupplierController::class, 'index'])->name('fnb.suppliers.index');
        Route::post('/admin/fnb/suppliers', [\App\Http\Controllers\Backend\Fnb\SupplierController::class, 'store'])->name('fnb.suppliers.store');
        Route::post('/admin/fnb/suppliers/{supplier}', [\App\Http\Controllers\Backend\Fnb\SupplierController::class, 'update'])->name('fnb.suppliers.update');
        Route::delete('/admin/fnb/suppliers/{supplier}', [\App\Http\Controllers\Backend\Fnb\SupplierController::class, 'destroy'])->name('fnb.suppliers.destroy');

        // Resep menu
        Route::get('/admin/fnb/recipes', [\App\Http\Controllers\Backend\Fnb\RecipeController::class, 'index'])->name('fnb.recipes.index');
        Route::get('/admin/fnb/recipes-data', [\App\Http\Controllers\Backend\Fnb\RecipeController::class, 'data'])->name('fnb.recipes.data');
        Route::get('/admin/fnb/recipes/{menu}', [\App\Http\Controllers\Backend\Fnb\RecipeController::class, 'show'])->name('fnb.recipes.show');
        Route::post('/admin/fnb/recipes/{menu}', [\App\Http\Controllers\Backend\Fnb\RecipeController::class, 'store'])->name('fnb.recipes.store');

        // Inventory: stok, pembelian, keluar manual, kartu stok
        Route::get('/admin/fnb/stock', [\App\Http\Controllers\Backend\Fnb\StockController::class, 'index'])->name('fnb.stock.index');
        Route::post('/admin/fnb/stock/purchase', [\App\Http\Controllers\Backend\Fnb\StockController::class, 'purchase'])->name('fnb.stock.purchase');
        Route::post('/admin/fnb/stock/issue', [\App\Http\Controllers\Backend\Fnb\StockController::class, 'issue'])->name('fnb.stock.issue');
        Route::get('/admin/fnb/stock/card', [\App\Http\Controllers\Backend\Fnb\StockController::class, 'card'])->name('fnb.stock.card');

        // Stok opname
        Route::get('/admin/fnb/opname', [\App\Http\Controllers\Backend\Fnb\StockOpnameController::class, 'index'])->name('fnb.opname.index');
        Route::post('/admin/fnb/opname', [\App\Http\Controllers\Backend\Fnb\StockOpnameController::class, 'store'])->name('fnb.opname.store');
    });

    Route::middleware(['can:view_data_master', 'subscribed', 'vertical:fnb'])->group(function () {
        Route::resource('/admin/categories', CategoriesController::class);
        Route::get('/admin/get-datacategories', [CategoriesController::class, 'getDataCategories'])->name('get-datacategories');
        // Daftar ringkas kategori (JSON) utk refresh dropdown di form menu tanpa reload.
        Route::get('/admin/categories-options', [CategoriesController::class, 'options'])->name('categories.options');

        // Import menu via CSV (template + upload) — didefinisikan SEBELUM resource
        // agar '/menus/template' & '/menus/import' tidak bentrok dgn '/menus/{menu}'.
        Route::get('/admin/menus/template', [MenuController::class, 'downloadTemplate'])->name('menus.template');
        Route::post('/admin/menus/import', [MenuController::class, 'importCsv'])->name('menus.import');

        Route::post('/admin/menus/mass-delete', [MenuController::class, 'massDestroy'])->name('menus.mass-delete');
        Route::resource('/admin/menus', MenuController::class);
        Route::get('/admin/get-datamenus', [MenuController::class, 'getDataMenus'])->name('get-datamenus');
        // Add-ons per menu (untuk form kelola & untuk kasir)
        Route::get('/admin/menus/{id}/addons', [MenuController::class, 'getAddons'])->name('menus.addons');

        // Promos — fitur paket Business (plan:promo)
        Route::middleware('plan:promo')->group(function () {
            Route::get('/admin/promos/data', [PromoController::class, 'getData'])->name('promos.data');
            Route::post('/admin/promos/toggle/{id}', [PromoController::class, 'toggleStatus'])->name('promos.toggle');
            Route::resource('/admin/promos', PromoController::class)
                ->except(['create', 'show'])
                ->names('promos');
        });

        // Manajemen Meja — fitur paket Enterprise ke atas (plan:tables)
        Route::middleware('plan:tables')->group(function () {
            Route::get('/admin/tables', [DiningTableController::class, 'index'])->name('tables.index');
            Route::get('/admin/get-datatables', [DiningTableController::class, 'getData'])->name('tables.data');
            Route::post('/admin/tables', [DiningTableController::class, 'store'])->name('tables.store');
            Route::put('/admin/tables/{id}', [DiningTableController::class, 'update'])->name('tables.update');
            Route::delete('/admin/tables/{id}', [DiningTableController::class, 'destroy'])->name('tables.destroy');
        });
    });

    // ====================================================
    // REPORTS: view_report — Superadmin, admin, kasir
    // ====================================================
    Route::middleware(['can:view_report', 'subscribed'])->group(function () {
        Route::get('/admin/reports/sales', [SalesReportController::class, 'index'])->name('reports.sales.index');
        Route::get('/admin/reports/sales/data', [SalesReportController::class, 'getData'])->name('reports.sales.data');
        Route::get('/admin/reports/sales/order/{id}', [SalesReportController::class, 'orderDetail'])->name('reports.sales.order');
        Route::get('/admin/reports/sales/print', [SalesReportController::class, 'print'])->name('reports.sales.print');

        // Laporan per-item — fitur paket Business (plan:report_items)
        Route::middleware('plan:report_items')->group(function () {
            Route::get('/admin/reports/items', [ItemSalesReportController::class, 'index'])->name('reports.items.index');
            Route::get('/admin/reports/items/data', [ItemSalesReportController::class, 'getData'])->name('reports.items.data');
            Route::get('/admin/reports/items/print', [ItemSalesReportController::class, 'print'])->name('reports.items.print');
        });
    });

    // ====================================================
    // PENGELUARAN (Expenses): view_expense — Superadmin, owner, admin, kasir
    // ====================================================
    Route::middleware(['can:view_expense', 'subscribed'])->group(function () {
        Route::get('/admin/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/admin/get-dataexpenses', [ExpenseController::class, 'getDataExpenses'])->name('expenses.data');
        Route::post('/admin/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/admin/expenses/{id}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/admin/expenses/{id}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // ====================================================
    // RESOURCES (User & Role Mgmt): view_resources — Superadmin only
    // ====================================================
    Route::middleware(['can:view_resources', 'subscribed'])->group(function () {
        // --- User management (owner boleh kelola staf-nya) ---
        Route::resource('/admin/users', UserController::class);
        Route::get('/admin/get-datauser', [UserController::class, 'getDataUsers'])->name('get-users');
        Route::post('/admin/users/mass-delete', [UserController::class, 'massDelete'])->name('users.mass-delete');
        Route::get('/admin/get-user-show-log/{id}', [UserController::class, 'getLoginSession'])->name('get-user-show-log');
        Route::get('/admin/get-user-show-log-activity/{id}', [UserController::class, 'getActivity'])->name('get-user-show-log-activity');
        // Nonaktifkan / aktifkan user (ban/unban) — KHUSUS Superadmin.
        Route::post('/admin/users/{id}/ban', [UserController::class, 'ban'])->middleware('role:Superadmin')->name('users.ban');
        Route::post('/admin/users/{id}/unban', [UserController::class, 'unban'])->middleware('role:Superadmin')->name('users.unban');
        Route::get('/admin/select/role', [RoleController::class, 'select'])->name('role.select');

        // --- Role / Hak Akses management: KHUSUS Superadmin (role bersifat global lintas-tenant) ---
        Route::middleware('role:Superadmin')->group(function () {
            Route::resource('/admin/roles', RoleController::class);
            Route::get('/admin/get-datarole', [RoleController::class, 'getDataRoles'])->name('get-datarole');
            Route::post('/admin/roles/mass-delete', [RoleController::class, 'massDelete'])->name('roles.mass-delete');
            Route::post('/admin/roles/generate-permissions', [RoleController::class, 'generatePermissions'])->name('roles.generate');
        });
    });

    // ====================================================
    // HELP (Log Activity): view_help — Superadmin, admin
    // ====================================================
    Route::middleware(['can:view_help', 'subscribed'])->group(function () {
        Route::resource('/admin/log-activity', LogActivityController::class);
        Route::get('/admin/get-datalogactivity', [LogActivityController::class, 'getDataLogActivity'])->name('get-datalogactivity');
    });
});

// Webhook langganan SaaS (billing) — tetap memakai Midtrans untuk pembayaran langganan tenant.
Route::post('/api/subscription-webhook', [BillingController::class, 'webhook']);

// Webhook DOKU (diteruskan oleh doku-gateway; gateway sudah verifikasi Bearer JWT).
// Menangani langganan (DSP-SUB-) & top-up deposit (DSP-DEP-).
Route::post('/api/doku-webhook', [BillingController::class, 'dokuWebhook']);

// Webhook Tripay (X-Callback-Signature diverifikasi di controller). Langganan (DSP-SUB-) & deposit (DSP-DEP-).
Route::post('/api/tripay-webhook', [BillingController::class, 'tripayWebhook']);

// Load Routes Authentication (Login, Register, Reset Password)
require __DIR__ . '/auth.php';
