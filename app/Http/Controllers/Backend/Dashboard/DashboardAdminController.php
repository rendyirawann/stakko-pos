<?php

namespace App\Http\Controllers\Backend\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\DailySalesTarget;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\DepositTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAdminController extends Controller
{
    public function index(Request $request)
    {
        // Superadmin default: dashboard ANALITIK platform (bukan kasir).
        // Bisa dialihkan ke mode kasir/POS lewat tombol (session sa_mode).
        if (auth()->user()->hasRole('Superadmin') && session('sa_mode', 'analytics') === 'analytics') {
            return $this->analytics();
        }

        /**
         * PERIODE DASHBOARD — harian (default) atau bulanan.
         *
         * range=day  -> dihitung untuk SATU tanggal (parameter `date`, format Y-m-d)
         * range=month-> dihitung untuk satu bulan penuh (parameter `month`, format Y-m)
         *
         * Default harian karena pemilik toko memantau hari berjalan, bukan rekap bulan.
         * Nama $monthStart/$monthEnd dipertahankan agar seluruh kueri di bawah tidak
         * perlu diubah; isinya kini "awal & akhir periode terpilih".
         */
        $range = $request->input('range') === 'month' ? 'month' : 'day';

        $selectedMonth = (string) $request->input('month', Carbon::now()->format('Y-m'));
        try {
            $monthAnchor = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        } catch (\Throwable $e) {
            $monthAnchor = Carbon::now()->startOfMonth();
        }
        $selectedMonth = $monthAnchor->format('Y-m');

        $selectedDate = (string) $request->input('date', Carbon::now()->format('Y-m-d'));
        try {
            $dayAnchor = Carbon::createFromFormat('Y-m-d', $selectedDate)->startOfDay();
        } catch (\Throwable $e) {
            $dayAnchor = Carbon::now()->startOfDay();
        }
        $selectedDate = $dayAnchor->format('Y-m-d');

        if ($range === 'month') {
            $monthStart = $monthAnchor->copy();
            $monthEnd   = $monthAnchor->copy()->endOfMonth();
            $periodLabel = $monthAnchor->locale('id')->translatedFormat('F Y');
        } else {
            $monthStart = $dayAnchor->copy();
            $monthEnd   = $dayAnchor->copy()->endOfDay();
            $periodLabel = $dayAnchor->locale('id')->translatedFormat('l, d F Y');
        }

        // Pilihan bulan untuk filter (12 bulan terakhir).
        $monthOptions = [];
        for ($c = Carbon::now()->startOfMonth(), $i = 0; $i < 12; $i++, $c->subMonth()) {
            $monthOptions[] = ['value' => $c->format('Y-m'), 'label' => $c->locale('id')->translatedFormat('F Y')];
        }

        // 1. Menu Tidak Tersedia / Habis (Real-time)
        $unavailableMenus = Menu::with('category')
            ->where('is_available', false)
            ->get();

        // 2. Top Selling Menus (Bulan Ini) - Real-time
        $topProducts = OrderDetail::with(['menu.category'])
            ->whereHas('order', function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->where('payment_status', 'paid')
                    ->whereNull('voided_at'); // pesanan salah tak dihitung
            })
            ->select('menu_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('menu_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 3. Data Grafik Penjualan vs Target (Bulan Ini) - Real-time
        // Ambil total penjualan per hari
        $actualSales = Order::whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereNull('voided_at') // pesanan salah tak dihitung ke grafik penjualan
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(grand_total) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'date');

        // Ambil target per hari
        $targets = DailySalesTarget::whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->pluck('amount', 'date');

        $dates = [];
        $salesSeries = [];
        $targetSeries = [];

        if ($range === 'day') {
            // Mode HARIAN: pecah per JAM. Satu batang untuk satu hari tidak berguna;
            // per jam justru memperlihatkan jam ramai — informasi yang dipakai untuk
            // mengatur stok & jadwal karyawan.
            $perJam = Order::whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('payment_status', 'paid')
                ->whereNull('voided_at')
                ->select(DB::raw('EXTRACT(HOUR FROM created_at) as jam'), DB::raw('SUM(grand_total) as total'))
                ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
                ->pluck('total', 'jam');

            // Rentang jam MENGIKUTI data, bukan dipatok 08:00-22:00 — toko yang buka
            // sampai lewat tengah malam kalau dipatok akan kehilangan transaksinya.
            $jamAda = collect($perJam->keys())->map(fn ($k) => (int) $k)->filter(fn ($k) => $k >= 0)->values();
            $jamAwal = $jamAda->isNotEmpty() ? max(0, $jamAda->min() - 1) : 8;
            $jamAkhir = $jamAda->isNotEmpty() ? min(23, $jamAda->max() + 1) : 22;

            $targetHarian = (int) DailySalesTarget::where('date', $monthStart->format('Y-m-d'))->value('amount');
            $jumlahJam = max(1, $jamAkhir - $jamAwal + 1);
            $targetPerJam = $targetHarian > 0 ? (int) round($targetHarian / $jumlahJam) : 0;

            for ($h = $jamAwal; $h <= $jamAkhir; $h++) {
                $dates[] = sprintf('%02d:00', $h);
                // Kunci hasil EXTRACT bisa berupa string/float tergantung driver.
                $nilai = $perJam[$h] ?? $perJam[(string) $h] ?? $perJam[(float) $h] ?? 0;
                $salesSeries[] = (int) $nilai;
                $targetSeries[] = $targetPerJam;
            }
        } else {
            // Mode BULANAN: per hari, sampai hari ini bila bulan berjalan.
            $chartEnd = $monthEnd->lt(Carbon::now()) ? $monthEnd->copy() : Carbon::now();
            for ($date = $monthStart->copy(); $date->lte($chartEnd); $date->addDay()) {
                $dateString = $date->format('Y-m-d');
                $dates[] = $date->format('d M');
                $salesSeries[] = (int) $actualSales->get($dateString, 0);
                $targetSeries[] = (int) $targets->get($dateString, 0);
            }
        }

        $chartData = [
            'categories' => $dates,
            'sales'      => $salesSeries,
            'targets'    => $targetSeries,
            'mode'       => $range,
        ];

        // 4. Quick Summary Widget - Real-time
        $revenue = Order::whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereNull('voided_at') // pesanan salah tak dihitung ke omzet
            ->sum('grand_total');

        $ordersCount = Order::whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereNull('voided_at') // pesanan salah tak dihitung
            ->count();

        $itemsSold = OrderDetail::whereHas('order', function ($q) use ($monthStart, $monthEnd) {
            $q->whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('payment_status', 'paid')
                ->whereNull('voided_at'); // pesanan salah tak dihitung
        })->sum('qty');

        // ===== MODUL HPP (paket Customize; Superadmin selalu lihat) =====
        // Modal bahan bulan ini + laba kotor/bersih + food cost %.
        $hppEnabled = auth()->user()?->isSuperadmin()
            || \App\Tenancy\Plan::tenantAllows(auth()->user()?->tenant, 'inventory_hpp');

        $totalHpp = 0.0;
        if ($hppEnabled) {
            $totalHpp = (float) OrderDetail::whereHas('order', function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->where('payment_status', 'paid')
                    ->whereNull('voided_at');
            })->sum('hpp');
        }

        // Pengeluaran bulan ini (untuk laba bersih).
        $monthExpense = (float) \App\Models\Expense::whereBetween('date', [
            $monthStart->toDateString(), $monthEnd->toDateString(),
        ])->sum('amount');

        $summary = [
            'revenue'      => $revenue,
            'orders_count' => $ordersCount,
            'items_sold'   => $itemsSold,
            // HPP & profitabilitas
            'hpp_enabled'   => $hppEnabled,
            'total_hpp'     => $totalHpp,
            'gross_profit'  => $revenue - $totalHpp,
            'net_profit'    => $revenue - $totalHpp - $monthExpense,
            'month_expense' => $monthExpense,
            'food_cost_pct' => $revenue > 0 ? round($totalHpp / $revenue * 100, 1) : 0,
        ];

        // Misi onboarding setup awal (deteksi otomatis selesai/belum).
        $setting = \App\Models\Setting::first();
        $onbSettings = $setting
            && trim((string) $setting->store_name) !== ''
            && trim((string) $setting->address) !== ''
            && ! empty($setting->printer_method)
            && (trim((string) $setting->receipt_header) !== '' || trim((string) $setting->receipt_footer) !== '');
        // Langkah 2 (data master) MENGIKUTI VERTICAL:
        //  - F&B     : butuh kategori + menu makanan/minuman
        //  - Laundry : butuh layanan laundry (kiloan/satuan/express) di Data Master → Layanan
        $onbTenant   = auth()->user()?->tenant;
        $onbIsLaundry = $onbTenant && method_exists($onbTenant, 'isLaundry') && $onbTenant->isLaundry();
        $onbMaster = $onbIsLaundry
            ? \App\Models\Laundry\LaundryService::count() > 0
            : (\App\Models\Category::count() > 0 && Menu::count() > 0);
        // Setup Karyawan: selesai bila tenant sudah punya akun ber-role owner, admin, DAN kasir.
        // Nama role lowercase (spatie); TenantScope global otomatis membatasi ke tenant aktif.
        $onbEmployees = \App\Models\User::role('owner')->exists()
            && \App\Models\User::role('admin')->exists()
            && \App\Models\User::role('kasir')->exists();
        // Semua langkah TAMPIL untuk semua role, tapi tombol "Setup Sekarang" hanya aktif bila
        // role user berwenang; selain itu tampil keterangan "Hanya ... yang mengatur ini".
        //   - Langkah 1 & 2 (Toko/Struk & Menu/Kategori): owner ATAU admin.
        //   - Langkah 3 (Setup Karyawan): owner saja.
        $u = auth()->user();
        $onbDone = (bool) ($onbSettings && $onbMaster && $onbEmployees);
        // Preferensi tampil/sembunyi panduan (per-tenant, di SiteOption).
        // Belum diset -> ikuti otomatis (tampil bila belum selesai). '1' = paksa tampil, '0' = sembunyi.
        $onbPref = $u->tenant_id ? \App\Models\SiteOption::get('dash_onboarding.' . $u->tenant_id) : null;
        $onbShow = $onbPref === null ? ! $onbDone : ($onbPref === '1');
        $onboarding = [
            'settings'      => (bool) $onbSettings,
            'master'        => (bool) $onbMaster,
            'employees'     => (bool) $onbEmployees,
            'can_store'     => (bool) ($u->hasRole('owner') || $u->hasRole('admin')),
            'can_employees' => (bool) $u->hasRole('owner'),
            'done'          => $onbDone,
            'show'          => (bool) $onbShow,
            'has_tenant'    => (bool) $u->tenant_id,
            // Label & tautan langkah 2 menyesuaikan vertical (F&B: Menu, Laundry: Layanan).
            'is_laundry'    => (bool) $onbIsLaundry,
            'master_title'  => $onbIsLaundry ? 'Setup Layanan Laundry' : 'Setup Menu & Kategori',
            'master_desc'   => $onbIsLaundry
                ? 'Tambah layanan cuci (kiloan/satuan/express) + harga & estimasi'
                : 'Tambah kategori + menu makanan & minuman',
            'master_url'    => $onbIsLaundry ? route('laundry.services.index') : route('menus.index'),
        ];

        return view('backend.dashboard.index', compact('unavailableMenus', 'topProducts', 'chartData', 'summary', 'selectedMonth', 'monthOptions', 'onboarding', 'range', 'selectedDate', 'periodLabel'));
    }

    /** Toggle tampil/sembunyi panduan "Setup Awal" di dashboard (preferensi per-tenant). */
    public function toggleOnboarding(\Illuminate\Http\Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        if ($tenantId) {
            \App\Models\SiteOption::set('dash_onboarding.' . $tenantId, $request->has('show') ? '1' : '0');
        }
        return back();
    }

    /** Alihkan tampilan Superadmin: 'analytics' (platform) <-> 'pos' (kasir). */
    public function switchMode(string $mode)
    {
        abort_unless(auth()->user()->hasRole('Superadmin'), 403);
        $mode = $mode === 'pos' ? 'pos' : 'analytics';
        session(['sa_mode' => $mode]);

        // Saat masuk mode POS: pastikan ADA tenant terpilih (default tenant pertama) agar
        // data ter-scope ke satu toko & tidak agregat/yatim (tenant_id NULL).
        if ($mode === 'pos' && ! session('sa_pos_tenant_id')) {
            $firstTenant = Tenant::orderBy('id')->value('id');
            if ($firstTenant) {
                session(['sa_pos_tenant_id' => $firstTenant]);
            }
        }

        return redirect()->route('dashboard');
    }

    /** Superadmin memilih toko/tenant untuk dioperasikan di mode POS/kasir. */
    public function setPosTenant($id)
    {
        abort_unless(auth()->user()->hasRole('Superadmin'), 403);
        $tenant = Tenant::find($id);
        abort_if(! $tenant, 404, 'Toko tidak ditemukan.');

        session(['sa_pos_tenant_id' => $tenant->id, 'sa_mode' => 'pos']);

        return redirect()->route('dashboard')->with('success', 'Mode kasir kini untuk toko: ' . $tenant->name);
    }

    /** Dashboard analitik platform untuk Superadmin (lintas tenant). */
    private function analytics()
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();
        $now        = Carbon::now();

        // Tenant mode bulanan yang masih aktif (langganan/trial belum kedaluwarsa).
        $monthlyActive = Tenant::where('billing_mode', '!=', 'deposit')
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->where(function ($x) use ($now) {
                    $x->where('subscription_status', 'active')->where('subscription_ends_at', '>', $now);
                })->orWhere(function ($x) use ($now) {
                    $x->where('subscription_status', 'trial')->where('trial_ends_at', '>', $now);
                });
            })->count();

        $stats = [
            'total_tenants'       => Tenant::count(),
            'active_tenants'      => Tenant::where('is_active', true)->count(),
            'deposit_tenants'     => Tenant::where('billing_mode', 'deposit')->count(),
            'monthly_tenants'     => Tenant::where('billing_mode', '!=', 'deposit')->count(),
            'monthly_active'      => $monthlyActive,
            'new_this_month'      => Tenant::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'platform_revenue'    => (float) Order::whereBetween('created_at', [$monthStart, $monthEnd])->where('payment_status', 'paid')->whereNull('voided_at')->sum('grand_total'),
            'platform_tx'         => (int) Order::whereBetween('created_at', [$monthStart, $monthEnd])->where('payment_status', 'paid')->whereNull('voided_at')->count(),
            'sub_revenue'         => (float) Subscription::where('status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount'),
            'deposit_outstanding' => (float) Tenant::sum('deposit_points'),
        ];

        // Grafik omzet platform harian (bulan ini)
        $dailyOmzet = Order::whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereNull('voided_at') // pesanan salah tak dihitung ke omzet platform
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(grand_total) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'date');

        $dates = [];
        $omzetSeries = [];
        for ($d = $monthStart->copy(); $d->lte($now); $d->addDay()) {
            $ds = $d->format('Y-m-d');
            $dates[] = $d->format('d M');
            $omzetSeries[] = (int) $dailyOmzet->get($ds, 0);
        }
        $chart = ['categories' => $dates, 'omzet' => $omzetSeries];

        // Top tenant berdasarkan omzet bulan ini
        $topRows = Order::whereBetween('created_at', [$monthStart, $monthEnd])
            ->where('payment_status', 'paid')
            ->whereNull('voided_at') // pesanan salah tak dihitung ke ranking omzet tenant
            ->whereNotNull('tenant_id')
            ->select('tenant_id', DB::raw('SUM(grand_total) as omzet'), DB::raw('COUNT(*) as tx'))
            ->groupBy('tenant_id')
            ->orderByDesc('omzet')
            ->limit(5)
            ->get();
        $names = Tenant::whereIn('id', $topRows->pluck('tenant_id'))->pluck('name', 'id');
        $topTenants = $topRows->map(fn ($r) => [
            'name'  => $names[$r->tenant_id] ?? ('Tenant #' . $r->tenant_id),
            'omzet' => (float) $r->omzet,
            'tx'    => (int) $r->tx,
        ]);

        $latestTenants = Tenant::orderByDesc('id')->limit(8)->get();

        $recentTopups = DepositTransaction::where('type', 'topup')
            ->with('tenant:id,name')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('backend.dashboard.analytics', compact('stats', 'chart', 'topTenants', 'latestTenants', 'recentTopups'));
    }

    /**
     * RINCIAN HPP PER MENU (JSON untuk DataTables di modal dashboard).
     * Menampilkan menu yang TERJUAL pada bulan terpilih beserta resep, modal (HPP) nyata,
     * omzet, laba, dan food cost per menu. Mengikuti filter bulan dashboard.
     */
    public function hppBreakdown(Request $request)
    {
        // Gate sama seperti kartu HPP: paket dgn modul inventory_hpp, atau Superadmin.
        $allowed = auth()->user()?->isSuperadmin()
            || \App\Tenancy\Plan::tenantAllows(auth()->user()?->tenant, 'inventory_hpp');
        abort_unless($allowed, 403, 'Fitur HPP tidak tersedia pada paket Anda.');

        // Menerima DUA format: 'Y-m' (periode bulanan) atau 'Y-m-d' (periode harian),
        // mengikuti mode dashboard yang sedang dipilih.
        $month = (string) $request->input('month', Carbon::now()->format('Y-m'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $month)) {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $month)->startOfDay();
            } catch (\Throwable $e) {
                $start = Carbon::now()->startOfDay();
            }
            $end = $start->copy()->endOfDay();
            $labelPeriode = $start->locale('id')->translatedFormat('l, d F Y');
        } else {
            try {
                $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable $e) {
                $start = Carbon::now()->startOfMonth();
            }
            $end = $start->copy()->endOfMonth();
            $labelPeriode = $start->locale('id')->translatedFormat('F Y');
        }

        // Agregasi per menu dari pesanan LUNAS & tidak dibatalkan.
        $rows = OrderDetail::query()
            ->selectRaw('menu_id,
                SUM(order_details.qty) AS qty_sold,
                SUM(order_details.subtotal) AS revenue,
                SUM(order_details.hpp) AS hpp_total')
            ->whereHas('order', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                    ->where('payment_status', 'paid')
                    ->whereNull('voided_at');
            })
            ->groupBy('menu_id')
            ->orderByDesc('revenue')
            ->get();

        // Resep tiap menu (bahan + gramasi) untuk kolom "Resep".
        $menus = Menu::whereIn('id', $rows->pluck('menu_id'))->with('menuIngredients.ingredient')->get()->keyBy('id');

        $data = $rows->map(function ($r) use ($menus) {
            $menu    = $menus->get($r->menu_id);
            $qty     = (float) $r->qty_sold;
            $revenue = (float) $r->revenue;
            $hpp     = (float) $r->hpp_total;

            $recipe = $menu
                ? $menu->menuIngredients->map(fn ($l) => [
                    'name' => $l->ingredient?->name,
                    'qty'  => rtrim(rtrim(number_format((float) $l->quantity, 2, '.', ''), '0'), '.'),
                    'unit' => $l->ingredient?->unit,
                ])->all()
                : [];

            return [
                'menu'        => $menu->name ?? 'Menu dihapus',
                'qty_sold'    => $qty,
                'revenue'     => $revenue,
                'hpp_total'   => $hpp,
                'hpp_per_pcs' => $qty > 0 ? round($hpp / $qty, 2) : 0,
                'profit'      => $revenue - $hpp,
                'food_cost'   => $revenue > 0 ? round($hpp / $revenue * 100, 1) : 0,
                'recipe'      => $recipe,
                'has_recipe'  => count($recipe) > 0,
            ];
        });

        return response()->json([
            'data'  => $data,
            'month' => $labelPeriode,
            'total' => [
                'revenue' => (float) $rows->sum('revenue'),
                'hpp'     => (float) $rows->sum('hpp_total'),
            ],
        ]);
    }
}