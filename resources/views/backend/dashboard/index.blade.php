@extends('backend.layout.app')
@section('title', 'Dashboard Analytics')
@section('content')

    @php
        // Label periode: dipakai di judul kartu KPI & grafik. Mengikuti mode terpilih.
        $selMonthLabel = $periodLabel
            ?? \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth ?? now()->format('Y-m'))->translatedFormat('F Y');
    @endphp

    <div id="kt_app_content" class="app-content flex-column-fluid mt-5">
        <div id="kt_app_content_container" class="app-container container-xxl">

            {{-- Penanda: bergabung lewat referral affiliate --}}
            @php
                $__tid = auth()->user()->tenant_id ?? null;
                $__ref = $__tid ? \App\Models\Referral::with('affiliate')->where('tenant_id', $__tid)->first() : null;
            @endphp
            @if ($__ref && $__ref->affiliate)
                <div class="d-flex align-items-center bg-light-primary border border-primary border-dashed rounded-3 mb-6 p-4">
                    <i class="ki-outline ki-share fs-2x text-primary me-3"></i>
                    <div>
                        <span class="fw-bold text-gray-900 d-block">Bergabung lewat kode referral: {{ $__ref->affiliate->code }}</span>
                        <span class="text-muted fs-7">Affiliate: {{ $__ref->affiliate->name }}{{ $__ref->affiliate->email ? ' · ' . $__ref->affiliate->email : '' }}</span>
                    </div>
                </div>
            @endif

            {{-- Welcome header --}}
            <div class="card border-0 shadow-sm mb-6 mb-xl-8"
                style="background: linear-gradient(120deg, #4f46e5 0%, #6366f1 55%, #818cf8 100%);">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between py-6">
                    <div class="text-white">
                        <h2 class="text-white fw-bold mb-1">Halo, {{ auth()->user()->name }} 👋</h2>
                        <div class="text-white opacity-75 fs-6">
                            {{ optional($currentTenant)->name ?? 'Mooda' }} •
                            <span class="fw-bold">{{ $periodLabel ?? \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</span>
                            <span class="badge badge-light-primary ms-1 fs-9">{{ ($range ?? 'day') === 'day' ? 'Harian' : 'Bulanan' }}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3 mt-sm-0 align-items-center flex-wrap">
                        {{-- FILTER PERIODE: harian (default) atau bulanan.
                             Harian memakai pemilih TANGGAL, bulanan memakai pilihan bulan. --}}
                        <form method="GET" class="me-1 d-flex align-items-center gap-2" id="form-periode">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Mode periode">
                                <input type="radio" class="btn-check" name="range" id="range-day" value="day"
                                    {{ ($range ?? 'day') === 'day' ? 'checked' : '' }} onchange="document.getElementById('form-periode').submit()">
                                <label class="btn btn-sm btn-light fw-bold" for="range-day">Harian</label>

                                <input type="radio" class="btn-check" name="range" id="range-month" value="month"
                                    {{ ($range ?? 'day') === 'month' ? 'checked' : '' }} onchange="document.getElementById('form-periode').submit()">
                                <label class="btn btn-sm btn-light fw-bold" for="range-month">Bulanan</label>
                            </div>

                            @if (($range ?? 'day') === 'day')
                                <input type="date" name="date" value="{{ $selectedDate ?? now()->format('Y-m-d') }}"
                                    max="{{ now()->format('Y-m-d') }}"
                                    class="form-control form-control-sm fw-bold border-0 text-gray-800"
                                    style="min-width:160px" title="Pilih tanggal"
                                    onchange="document.getElementById('form-periode').submit()">
                                {{-- bulan tetap dibawa agar tidak hilang saat berpindah mode --}}
                                <input type="hidden" name="month" value="{{ $selectedMonth ?? now()->format('Y-m') }}">
                            @else
                                <select name="month" class="form-select form-select-sm fw-bold border-0 text-gray-800"
                                    onchange="document.getElementById('form-periode').submit()" style="min-width: 150px;" title="Pilih bulan">
                                    @foreach (($monthOptions ?? []) as $opt)
                                        <option value="{{ $opt['value'] }}" {{ ($selectedMonth ?? '') === $opt['value'] ? 'selected' : '' }}>
                                            {{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="date" value="{{ $selectedDate ?? now()->format('Y-m-d') }}">
                            @endif
                        </form>
                        @if ($isSuperadminView ?? false)
                            <a href="{{ route('view-mode.switch', 'analytics') }}" class="btn btn-light fw-bold">
                                <i class="ki-outline ki-chart-simple fs-3 me-1"></i> Dashboard Analitik
                            </a>
                        @endif
                        @can('view_kasir')
                            <a href="{{ route('kasir.index') }}" class="btn btn-light fw-bold">
                                <i class="ki-outline ki-handcart fs-3 me-1"></i> Buka Kasir
                            </a>
                        @endcan
                        <a href="{{ route('download-app') }}" class="btn btn-active-light text-white border border-white border-opacity-25 fw-bold">
                            <i class="ki-outline ki-tablet fs-3 me-1"></i> Aplikasi Tablet
                        </a>
                    </div>
                </div>
            </div>

            @php
                $avgPerOrder = ($summary['orders_count'] ?? 0) > 0
                    ? ($summary['revenue'] / $summary['orders_count'])
                    : 0;
            @endphp

            {{-- Toggle tampil/sembunyi panduan Setup Awal (preferensi per-tenant, tetap terlihat walau kartu disembunyikan) --}}
            @if (isset($onboarding) && $onboarding['has_tenant'])
                <div class="d-flex justify-content-end align-items-center mb-3">
                    <form method="POST" action="{{ route('dashboard.onboarding-toggle') }}" id="onb-toggle-form" class="m-0">
                        @csrf
                        <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                            <input class="form-check-input" type="checkbox" name="show" value="1"
                                {{ $onboarding['show'] ? 'checked' : '' }}
                                onchange="document.getElementById('onb-toggle-form').submit()">
                            <span class="form-check-label fw-semibold text-gray-600 fs-8 ms-2">Panduan Setup Awal</span>
                        </label>
                    </form>
                </div>
            @endif

            {{-- ONBOARDING: misi setup awal (otomatis tercentang saat selesai; tampil/sembunyi via toggle di atas). --}}
            @if (isset($onboarding) && $onboarding['show'])
                <div class="card border-0 shadow-sm mb-6 mb-xl-8 border-start border-4 border-primary" id="onboarding-card">
                    <div class="card-body p-6">
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="badge badge-primary">Setup Awal</span>
                            <h3 class="fw-bold text-gray-900 m-0">Selesaikan setup kasir POS Anda 🚀</h3>
                            <span class="badge badge-light-primary ms-auto">{{ ($onboarding['settings'] ? 1 : 0) + ($onboarding['master'] ? 1 : 0) + ($onboarding['employees'] ? 1 : 0) }}/3 selesai</span>
                        </div>
                        <p class="text-muted fs-7 mb-5">Lengkapi langkah berikut agar kasir siap dipakai. Progres tercentang otomatis saat selesai.</p>

                        {{-- Misi 1 --}}
                        <div class="d-flex align-items-center justify-content-between border rounded p-4 mb-3 {{ $onboarding['settings'] ? 'bg-light-success border-success' : 'bg-light' }}">
                            <div class="d-flex align-items-center">
                                @if ($onboarding['settings'])
                                    <i class="ki-outline ki-check-circle fs-2x text-success me-3"></i>
                                @else
                                    <span class="badge badge-circle badge-primary me-3 fs-6" style="width:34px;height:34px">1</span>
                                @endif
                                <div>
                                    <div class="fw-bold text-gray-900">Setup Toko &amp; Struk</div>
                                    <div class="fs-8 text-muted">Nama toko, alamat, layout struk &amp; printer</div>
                                </div>
                            </div>
                            @if ($onboarding['settings'])
                                <span class="badge badge-light-success"><i class="ki-outline ki-check fs-6 me-1"></i>Selesai</span>
                            @elseif ($onboarding['can_store'])
                                <a href="{{ route('settings.index') }}" class="btn btn-sm btn-primary text-nowrap">Setup Sekarang</a>
                            @else
                                <span class="badge badge-light-secondary text-nowrap"><i class="ki-outline ki-lock-2 fs-7 me-1"></i>Hanya Owner/Admin yang mengatur ini</span>
                            @endif
                        </div>

                        {{-- Misi 2 --}}
                        <div class="d-flex align-items-center justify-content-between border rounded p-4 mb-3 {{ $onboarding['master'] ? 'bg-light-success border-success' : 'bg-light' }}">
                            <div class="d-flex align-items-center">
                                @if ($onboarding['master'])
                                    <i class="ki-outline ki-check-circle fs-2x text-success me-3"></i>
                                @else
                                    <span class="badge badge-circle badge-primary me-3 fs-6" style="width:34px;height:34px">2</span>
                                @endif
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $onboarding['master_title'] ?? 'Setup Menu & Kategori' }}</div>
                                    <div class="fs-8 text-muted">{{ $onboarding['master_desc'] ?? 'Tambah kategori + menu makanan & minuman' }}</div>
                                </div>
                            </div>
                            @if ($onboarding['master'])
                                <span class="badge badge-light-success"><i class="ki-outline ki-check fs-6 me-1"></i>Selesai</span>
                            @elseif ($onboarding['can_store'])
                                <a href="{{ $onboarding['master_url'] ?? route('menus.index') }}" class="btn btn-sm btn-primary text-nowrap">Setup Sekarang</a>
                            @else
                                <span class="badge badge-light-secondary text-nowrap"><i class="ki-outline ki-lock-2 fs-7 me-1"></i>Hanya Owner/Admin yang mengatur ini</span>
                            @endif
                        </div>

                        {{-- Misi 3 --}}
                        <div class="d-flex align-items-center justify-content-between border rounded p-4 {{ $onboarding['employees'] ? 'bg-light-success border-success' : 'bg-light' }}">
                            <div class="d-flex align-items-center">
                                @if ($onboarding['employees'])
                                    <i class="ki-outline ki-check-circle fs-2x text-success me-3"></i>
                                @else
                                    <span class="badge badge-circle badge-primary me-3 fs-6" style="width:34px;height:34px">3</span>
                                @endif
                                <div>
                                    <div class="fw-bold text-gray-900">Setup Karyawan</div>
                                    <div class="fs-8 text-muted">Buat akun Owner, Admin &amp; Kasir</div>
                                </div>
                            </div>
                            @if ($onboarding['employees'])
                                <span class="badge badge-light-success"><i class="ki-outline ki-check fs-6 me-1"></i>Selesai</span>
                            @elseif ($onboarding['can_employees'])
                                <a href="{{ route('users.index') }}" class="btn btn-sm btn-primary text-nowrap">Setup Sekarang</a>
                            @else
                                <span class="badge badge-light-secondary text-nowrap"><i class="ki-outline ki-lock-2 fs-7 me-1"></i>Hanya Owner yang mengatur ini</span>
                            @endif
                        </div>

                        <div class="text-end mt-4">
                            <button type="button" id="onboarding-dismiss" class="btn btn-sm btn-light-secondary">
                                <i class="ki-outline ki-eye-slash fs-6 me-1"></i>Jangan tampilkan lagi (30 hari)
                            </button>
                        </div>
                    </div>
                </div>
                <script>
                    (function () {
                        var card = document.getElementById('onboarding-card');
                        var btn = document.getElementById('onboarding-dismiss');
                        if (!card) return;
                        var KEY = 'mooda_onboarding_dismissed_at';
                        var THIRTY = 30 * 24 * 60 * 60 * 1000; // 30 hari
                        try {
                            var at = parseInt(localStorage.getItem(KEY) || '0', 10);
                            if (at && (Date.now() - at) < THIRTY) { card.style.display = 'none'; }
                        } catch (e) {}
                        if (btn) btn.addEventListener('click', function () {
                            try { localStorage.setItem(KEY, String(Date.now())); } catch (e) {}
                            card.style.display = 'none';
                        });
                    })();
                </script>
            @endif

            <div class="row g-5 g-xl-10 mb-xl-10">
                <div class="col-6 col-md-3">
                    <div class="card bg-light-primary border-0 shadow-sm h-100">
                        <div class="card-body p-6">
                            <div class="fs-6 fw-semibold text-primary mb-2">Total Omzet ({{ $selMonthLabel }})</div>
                            <div class="fs-2hx fw-bold text-gray-800">Rp
                                {{ number_format($summary['revenue'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-light-success border-0 shadow-sm h-100">
                        <div class="card-body p-6">
                            <div class="fs-6 fw-semibold text-success mb-2">Jumlah Transaksi ({{ $selMonthLabel }})</div>
                            <div class="fs-2hx fw-bold text-gray-800">
                                {{ number_format($summary['orders_count'] ?? 0, 0, ',', '.') }} <span
                                    class="fs-4 text-muted">Transaksi</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-light-warning border-0 shadow-sm h-100">
                        <div class="card-body p-6">
                            <div class="fs-6 fw-semibold text-warning mb-2">Rata-rata / Transaksi</div>
                            <div class="fs-2hx fw-bold text-gray-800">Rp
                                {{ number_format($avgPerOrder, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-light-info border-0 shadow-sm h-100">
                        <div class="card-body p-6">
                            <div class="fs-6 fw-semibold text-info mb-2">Porsi Terjual</div>
                            <div class="fs-2hx fw-bold text-gray-800">
                                {{ number_format($summary['items_sold'], 0, ',', '.') }} <span
                                    class="fs-4 text-muted">Porsi</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- MODUL HPP (paket Customize / Superadmin): modal bahan & profitabilitas bulan ini --}}
            @if (!empty($summary['hpp_enabled']))
                <div class="row g-5 g-xl-10 mt-2">
                    <div class="col-6 col-md-3">
                        {{-- Bisa diklik: buka rincian HPP per menu (resep + modal + laba) --}}
                        <div class="card bg-light-warning border-0 shadow-sm h-100 cursor-pointer" id="card-hpp-detail"
                             title="Klik untuk melihat rincian HPP per menu">
                            <div class="card-body p-6 position-relative">
                                <div class="fs-6 fw-semibold text-warning mb-2">Total HPP (modal bahan)</div>
                                <div class="fs-2hx fw-bold text-gray-800">Rp {{ number_format($summary['total_hpp'], 0, ',', '.') }}</div>
                                <div class="fs-8 text-muted mt-1">
                                    <i class="ki-outline ki-eye fs-6 me-1"></i>klik untuk rincian per menu
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card bg-light-primary border-0 shadow-sm h-100">
                            <div class="card-body p-6">
                                <div class="fs-6 fw-semibold text-primary mb-2">Laba Kotor</div>
                                <div class="fs-2hx fw-bold text-gray-800">Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</div>
                                <div class="fs-8 text-muted mt-1">omzet − HPP</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card bg-light-success border-0 shadow-sm h-100">
                            <div class="card-body p-6">
                                <div class="fs-6 fw-semibold text-success mb-2">Laba Bersih</div>
                                <div class="fs-2hx fw-bold text-gray-800">Rp {{ number_format($summary['net_profit'], 0, ',', '.') }}</div>
                                <div class="fs-8 text-muted mt-1">− pengeluaran Rp {{ number_format($summary['month_expense'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card bg-light-danger border-0 shadow-sm h-100">
                            <div class="card-body p-6">
                                <div class="fs-6 fw-semibold text-danger mb-2">Food Cost</div>
                                <div class="fs-2hx fw-bold text-gray-800">{{ $summary['food_cost_pct'] }}%</div>
                                <div class="fs-8 text-muted mt-1">ideal ≤ 35%</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-5 g-xl-10 mb-xl-10 mt-5">
                <div class="col-xl-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header pt-5 border-0">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">{{ ($range ?? 'day') === 'day' ? 'Performa Jam per Jam' : 'Performa Restoran Harian' }}</span>
                                <span class="text-muted fw-semibold fs-7">
                                    {{ ($range ?? 'day') === 'day' ? 'Omzet per Jam' : 'Omzet Aktual vs Target' }} — {{ $selMonthLabel }}
                                </span>
                            </h3>
                        </div>
                        <div class="card-body pt-2 pb-0 ps-0">
                            <div id="kt_sales_chart" style="height: 350px"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header pt-5 border-0">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Menu Paling Laku</span>
                                <span class="text-muted fw-semibold fs-7">Top 5 Penjualan Terbanyak Bulan Ini</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            @forelse($topProducts as $top)
                                <div class="d-flex flex-stack mb-6">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol py-2 symbol-40px me-4">
                                            <span
                                                class="symbol-label bg-light-primary text-primary fw-bold">{{ $loop->iteration }}</span>
                                        </div>
                                        <div class="d-flex flex-column justify-content-center">
                                            <a href="#"
                                                class="fs-6 text-gray-800 text-hover-primary fw-bold mb-1">{{ $top->menu->name ?? 'Menu Dihapus' }}</a>
                                            <div class="fw-semibold text-gray-400 fs-8">
                                                {{ $top->menu->category->name ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="fs-5 fw-bolder text-gray-800">{{ $top->total_qty }} <span
                                                class="fs-8 fw-normal text-muted">Porsi</span></div>
                                        <div class="fs-7 fw-bold text-success">Rp
                                            {{ number_format($top->total_revenue, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-10">Belum ada data pesanan bulan ini.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1 text-danger"><i
                                        class="ki-outline ki-warning-2 fs-2 text-danger me-2"></i> Daftar Menu Habis</span>
                                <span class="text-muted fw-semibold fs-7">Menu yang saat ini tidak tersedia untuk
                                    dipesan</span>
                            </h3>
                            <div class="card-toolbar">
                                <a href="#" class="btn btn-sm btn-light-primary">Kelola Menu</a>
                            </div>
                        </div>
                        <div class="card-body py-3">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted bg-light">
                                            <th class="ps-4 rounded-start">Kategori</th>
                                            <th>Nama Menu</th>
                                            <th class="text-end pe-4 rounded-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($unavailableMenus as $menu)
                                            <tr>
                                                <td class="ps-4">
                                                    <span
                                                        class="badge badge-light-dark">{{ $menu->category->name ?? '-' }}</span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-gray-800">{{ $menu->name }}</div>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <span class="text-danger fw-bold"><i
                                                            class="ki-outline ki-cross-circle fs-5 text-danger"></i>
                                                        Habis</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-5">Semua menu saat ini
                                                    tersedia! 🎉</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ============ MODAL RINCIAN HPP PER MENU (DataTables ajax) ============ --}}
    @if (!empty($summary['hpp_enabled']))
    <div class="modal fade" id="modal-hpp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header py-4">
                    <div>
                        <h3 class="fw-bold mb-0">Rincian HPP per Menu</h3>
                        <span class="text-muted fs-8">Menu yang terjual pada <b id="hpp-month">—</b> · modal bahan nyata (FIFO/FEFO)</span>
                    </div>
                    <div class="btn btn-icon btn-sm btn-active-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-1"></i></div>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="bg-light-primary rounded p-4">
                                <div class="fs-8 text-muted fw-bold text-uppercase">Omzet</div>
                                <div class="fs-4 fw-bolder text-gray-900" id="hpp-sum-revenue">Rp 0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bg-light-warning rounded p-4">
                                <div class="fs-8 text-muted fw-bold text-uppercase">Total HPP</div>
                                <div class="fs-4 fw-bolder text-gray-900" id="hpp-sum-hpp">Rp 0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bg-light-success rounded p-4">
                                <div class="fs-8 text-muted fw-bold text-uppercase">Laba Kotor</div>
                                <div class="fs-4 fw-bolder text-gray-900" id="hpp-sum-profit">Rp 0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bg-light-danger rounded p-4">
                                <div class="fs-8 text-muted fw-bold text-uppercase">Food Cost</div>
                                <div class="fs-4 fw-bolder text-gray-900" id="hpp-sum-fc">0%</div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle gy-3" id="tbl-hpp">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th class="ps-4">Menu</th>
                                    <th>Resep (bahan per porsi)</th>
                                    <th class="text-end">Terjual</th>
                                    <th class="text-end">Omzet</th>
                                    <th class="text-end">HPP/porsi</th>
                                    <th class="text-end">Total HPP</th>
                                    <th class="text-end">Laba</th>
                                    <th class="text-end pe-4">Food Cost</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="fs-8 text-muted mt-3">
                        Menu tanpa resep akan menampilkan HPP Rp 0 — atur resepnya di
                        <a href="{{ route('fnb.recipes.index') }}" class="fw-bold">HPP &amp; Inventory → Resep</a>.
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var chartData = @json($chartData);
            var element = document.getElementById('kt_sales_chart');

            if (!element) return;

            var options = {
                series: [{
                    name: 'Omzet Aktual',
                    type: 'column',
                    data: chartData.sales
                }, {
                    name: 'Target Penjualan',
                    type: 'line',
                    data: chartData.targets
                }],
                chart: {
                    height: 350,
                    type: 'line',
                    fontFamily: 'Inter, sans-serif',
                    toolbar: {
                        show: false
                    }
                },
                stroke: {
                    width: [0, 4],
                    curve: 'smooth'
                },
                dataLabels: {
                    enabled: false
                },
                labels: chartData.categories,
                xaxis: {
                    type: 'category',
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
                    labels: {
                        style: {
                            colors: '#a1a5b7',
                            fontSize: '12px'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function(value) {
                            return "Rp " + (value || 0).toLocaleString('id-ID');
                        },
                        style: {
                            colors: '#a1a5b7',
                            fontSize: '12px'
                        }
                    }
                },
                colors: ['#4f46e5', '#f1416c'],
                fill: {
                    opacity: [0.85, 1]
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right'
                },
                grid: {
                    borderColor: '#eff2f5',
                    strokeDashArray: 4,
                    yaxis: {
                        lines: {
                            show: true
                        }
                    }
                }
            };

            var chart = new ApexCharts(element, options);
            chart.render();
        });

        // ============ RINCIAN HPP PER MENU (modal + DataTables ajax) ============
        (function () {
            const card = document.getElementById('card-hpp-detail');
            if (!card) return;
            const rp = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
            const esc = t => $('<div>').text(t == null ? '' : t).html();
            let dt = null;

            /**
             * datatables.bundle.js (2,4MB) sengaja TIDAK dimuat di dashboard.
             * Muat sekali saja saat kartu HPP pertama kali diklik, supaya dashboard tetap ringan.
             */
            let dtReady = null;
            function ensureDataTables() {
                if (window.jQuery && $.fn.DataTable) return Promise.resolve();
                if (dtReady) return dtReady;
                dtReady = new Promise(function (resolve, reject) {
                    const css = document.createElement('link');
                    css.rel = 'stylesheet';
                    css.href = "{{ URL::to('assets/plugins/custom/datatables/datatables.bundle.css') }}";
                    document.head.appendChild(css);
                    const js = document.createElement('script');
                    js.src = "{{ URL::to('assets/plugins/custom/datatables/datatables.bundle.js') }}";
                    js.onload = resolve;
                    js.onerror = () => reject(new Error('Gagal memuat DataTables'));
                    document.body.appendChild(js);
                });
                return dtReady;
            }

            card.addEventListener('click', function () {
                // Rincian HPP mengikuti periode dashboard: mode harian -> tanggal itu saja.
                const isHarian = {{ ($range ?? 'day') === 'day' ? 'true' : 'false' }};
                const month = isHarian
                    ? '{{ $selectedDate ?? '' }}'
                    : ($('select[name="month"]').val() || '{{ $selectedMonth ?? '' }}');
                new bootstrap.Modal(document.getElementById('modal-hpp')).show();

                const body = document.querySelector('#tbl-hpp tbody');
                if (!dt && body) {
                    body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-10">Memuat…</td></tr>';
                }

                ensureDataTables().then(function () {
                if (dt) { dt.ajax.url("{{ route('dashboard.hpp-breakdown') }}?month=" + month).load(); return; }

                dt = $('#tbl-hpp').DataTable({
                    ajax: {
                        url: "{{ route('dashboard.hpp-breakdown') }}?month=" + month,
                        dataSrc: function (json) {
                            const rev = json.total.revenue, hpp = json.total.hpp;
                            $('#hpp-month').text(json.month || '-');
                            $('#hpp-sum-revenue').text(rp(rev));
                            $('#hpp-sum-hpp').text(rp(hpp));
                            $('#hpp-sum-profit').text(rp(rev - hpp));
                            $('#hpp-sum-fc').text((rev > 0 ? Math.round(hpp / rev * 1000) / 10 : 0) + '%');
                            return json.data;
                        },
                    },
                    order: [[3, 'desc']],
                    pageLength: 10,
                    lengthChange: false,
                    language: {
                        search: 'Cari menu:', zeroRecords: 'Belum ada menu terjual pada bulan ini.',
                        info: 'Menampilkan _START_-_END_ dari _TOTAL_ menu', infoEmpty: 'Tidak ada data',
                        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                    },
                    columns: [
                        { data: 'menu', render: d => '<span class="fw-bold text-gray-800">' + esc(d) + '</span>' },
                        {
                            data: 'recipe', orderable: false, render: function (r) {
                                if (!r || !r.length) return '<span class="badge badge-light-warning fs-9">Belum ada resep</span>';
                                return r.map(x => '<span class="badge badge-light-primary fs-9 me-1 mb-1">' + esc(x.name) + ' ' + esc(x.qty) + esc(x.unit || '') + '</span>').join('');
                            }
                        },
                        { data: 'qty_sold', className: 'text-end', render: d => Number(d).toLocaleString('id-ID') },
                        { data: 'revenue', className: 'text-end', render: d => rp(d) },
                        { data: 'hpp_per_pcs', className: 'text-end', render: d => rp(d) },
                        { data: 'hpp_total', className: 'text-end fw-bold', render: d => rp(d) },
                        { data: 'profit', className: 'text-end fw-bold', render: d => '<span class="text-success">' + rp(d) + '</span>' },
                        {
                            data: 'food_cost', className: 'text-end pe-4', render: function (d, t, row) {
                                if (!row.has_recipe) return '<span class="text-muted">—</span>';
                                const cls = d > 35 ? 'danger' : (d > 0 ? 'success' : 'secondary');
                                return '<span class="badge badge-light-' + cls + '">' + d + '%</span>';
                            }
                        },
                    ],
                });
                }).catch(function () {
                    const b = document.querySelector('#tbl-hpp tbody');
                    if (b) b.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-10">Gagal memuat tabel. Coba muat ulang halaman.</td></tr>';
                });
            });
        })();

    </script>
    @endpush

{{--
    Ajakan pasang APK — muncul setelah login, di dashboard.

    Alasan keberadaannya: pengguna BROWSER tidak pernah diberi tahu bahwa aplikasinya ada.
    Popup di halaman login hanya menyala untuk yang SUDAH di dalam APK dengan versi lama
    (deteksi "MoodaAPK/<code>"), sehingga kasir yang memakai Chrome tak pernah tahu — dan
    di Chrome, koneksi printer Bluetooth memang rapuh (Web Bluetooth melupakan perangkat
    antar halaman tanpa flag khusus). Di APK, socket dipegang Android sehingga tetap hidup.
--}}
@php
    $ua = request()->userAgent() ?? '';
    // Hanya Android: APK tak bisa dipasang di iOS/desktop, jadi jangan diajak sia-sia.
    // Di dalam APK sendiri UA memuat "MoodaAPK/" -> tidak perlu diajak lagi.
    // Rute unduhan dijaga middleware 'subscribed'; tenant tanpa akses aktif akan dilempar
    // ke billing, jadi jangan ditawari sesuatu yang tak bisa ia buka.
    $showApkAd = auth()->check()
        && !auth()->user()->isSuperadmin()
        && str_contains($ua, 'Android')
        && !str_contains($ua, 'MoodaAPK/')
        && optional(auth()->user()->tenant)->hasActiveAccess();
@endphp

@if ($showApkAd)
    @push('scripts')
        <script>
            (function () {
                var KEY = 'mooda_apk_ad_until';   // tunda sampai kapan (epoch ms)
                var HARI = 24 * 60 * 60 * 1000;

                function tunda(hari) {
                    try { localStorage.setItem(KEY, String(Date.now() + hari * HARI)); } catch (e) {}
                }

                try {
                    var until = parseInt(localStorage.getItem(KEY) || '0', 10);
                    if (until && Date.now() < until) return;
                } catch (e) { /* localStorage diblokir -> tampilkan saja */ }

                document.addEventListener('DOMContentLoaded', function () {
                    if (!window.Swal) return;

                    // Beri jeda: halaman sempat tampil dulu, dan modal yang lebih mendesak
                    // (mis. "Shift Belum Ditutup") tidak tertimpa — Swal.fire menggantikan
                    // modal yang sedang terbuka, bukan mengantre.
                    setTimeout(function () {
                        if (Swal.isVisible()) return;

                        Swal.fire({
                            imageUrl: "{{ asset('assets/media/logos/mooda-logo.png') }}",
                            imageWidth: 150,
                            imageAlt: 'Mooda',
                            title: 'Pakai Aplikasi Mooda Sekarang',
                            html: '<div class="text-start fs-6 text-gray-700 mt-3">'
                                + '<div class="d-flex mb-2"><i class="ki-outline ki-printer fs-3 text-primary me-3 mt-1"></i>'
                                + '<span>Printer Bluetooth <b>tetap tersambung</b> — tak perlu klik "Hubungkan" berulang</span></div>'
                                + '<div class="d-flex mb-2"><i class="ki-outline ki-rocket fs-3 text-primary me-3 mt-1"></i>'
                                + '<span>Lebih ringan &amp; cepat dibanding lewat browser</span></div>'
                                + '<div class="d-flex"><i class="ki-outline ki-screen fs-3 text-primary me-3 mt-1"></i>'
                                + '<span>Layar penuh, tanpa bilah alamat browser</span></div>'
                                + '</div>',
                            confirmButtonText: 'Pasang Sekarang',
                            showCancelButton: true,
                            cancelButtonText: 'Nanti',
                            allowOutsideClick: false,
                            customClass: {
                                confirmButton: 'btn btn-primary fw-bold',
                                cancelButton: 'btn btn-light fw-bold',
                            },
                            buttonsStyling: false,
                        }).then(function (r) {
                            if (r.isConfirmed) {
                                // Diarahkan ke halaman Aplikasi (bukan langsung unduh berkas),
                                // supaya kasir membaca cara pasang & izin "sumber tidak dikenal".
                                tunda(14);
                                window.location.href = "{{ route('download-app') }}";
                            } else {
                                tunda(3);
                            }
                        });
                    }, 1200);
                });
            })();
        </script>
    @endpush
@endif
