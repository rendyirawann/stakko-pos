<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="275px"
    data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_toggle">
    <div class="d-flex flex-stack px-4 px-lg-6 py-3 py-lg-8" id="kt_app_sidebar_logo">
        <a href="{{ route('dashboard') }}">
            <img alt="Mooda" src="{{ asset('assets/media/logos/mooda-logo.png') }}"
                class="h-40px h-lg-60px theme-light-show" />
            <img alt="Mooda" src="{{ asset('assets/media/logos/mooda-logo-white.png') }}"
                class="h-40px h-lg-60px theme-dark-show" />
        </a>
        <div class="ms-3">
            <div class="cursor-pointer position-relative symbol symbol-circle symbol-40px"
                data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent"
                data-kt-menu-placement="bottom-end">
                <img src="{{ Auth::user()->avatar ? asset('storage/user/avatar/' . Auth::user()->avatar) : asset('assets/media/logos/mooda-mark-192.png') }}" alt="user" />
                <div class="position-absolute rounded-circle bg-success start-100 top-100 h-8px w-8px ms-n3 mt-n3">
                </div>
            </div>
            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
                data-kt-menu="true">
                <div class="menu-item px-3">
                    <div class="menu-content d-flex align-items-center px-3">
                        <div class="symbol symbol-50px me-5">
                            <img alt="Logo" src="{{ Auth::user()->avatar ? asset('storage/user/avatar/' . Auth::user()->avatar) : asset('assets/media/logos/mooda-mark-192.png') }}" />
                        </div>
                        <div class="d-flex flex-column">
                            <div class="fw-bold d-flex align-items-center fs-5">{{ Auth::user()->name ?? 'User' }}
                                <span
                                    class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">{{ Auth::user()->roles->pluck('name')->first() ?? 'Staff' }}</span>
                            </div>
                            <a href="#"
                                class="fw-semibold text-muted text-hover-primary fs-7">{{ Auth::user()->email ?? '' }}</a>
                        </div>
                    </div>
                </div>
                <div class="separator my-2"></div>
                <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                    data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                    <a href="#" class="menu-link px-5">
                        <span class="menu-title position-relative">Mode
                            <span class="ms-5 position-absolute translate-middle-y top-50 end-0">
                                <i class="ki-outline ki-night-day theme-light-show fs-2"></i>
                                <i class="ki-outline ki-moon theme-dark-show fs-2"></i>
                            </span></span>
                    </a>
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px"
                        data-kt-menu="true" data-kt-element="theme-mode-menu">
                        <div class="menu-item px-3 my-0">
                            <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                                <span class="menu-icon" data-kt-element="icon">
                                    <i class="ki-outline ki-night-day fs-2"></i>
                                </span>
                                <span class="menu-title">Light</span>
                            </a>
                        </div>
                        <div class="menu-item px-3 my-0">
                            <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                                <span class="menu-icon" data-kt-element="icon">
                                    <i class="ki-outline ki-moon fs-2"></i>
                                </span>
                                <span class="menu-title">Dark</span>
                            </a>
                        </div>
                        <div class="menu-item px-3 my-0">
                            <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                                <span class="menu-icon" data-kt-element="icon">
                                    <i class="ki-outline ki-screen fs-2"></i>
                                </span>
                                <span class="menu-title">System</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="menu-item px-5 my-1">
                    <a href="{{ route('account.index') }}" class="menu-link px-5">My Profile</a>
                </div>
                <div class="menu-item px-5">
                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
                        @csrf
                        <a href="#" id="logout-btn" class="menu-link px-5">
                            <span class="menu-icon"><i class="ki-outline ki-exit-right fs-2 text-danger"></i></span>
                            Sign Out
                        </a>
                    </form>
                </div>

                <script>
                    document.getElementById('logout-btn').addEventListener('click', function(e) {
                        e.preventDefault();

                        Swal.fire({
                            icon: 'question',
                            title: '<span class="fw-bold">Keluar dari Akun?</span>',
                            html: '<span class="text-muted fs-6">Sesi Anda akan diakhiri. Sampai jumpa lagi! 👋</span>',
                            showCancelButton: true,
                            confirmButtonText: '<i class="ki-outline ki-exit-right fs-5 me-1"></i> Ya, Sign Out',
                            cancelButtonText: 'Batal',
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: 'btn btn-danger me-3',
                                cancelButton: 'btn btn-light'
                            },
                            reverseButtons: true,
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Inject dot-loader style once
                                if (!document.getElementById('dot-loader-style-logout')) {
                                    const s = document.createElement('style');
                                    s.id = 'dot-loader-style-logout';
                                    s.textContent = `
                                        .dot-out { width: 12px; height: 12px; background-color: #f1416c; border-radius: 50%; animation: bounceOut 0.6s infinite alternate; }
                                        .dot-out--2 { animation-delay: 0.15s; }
                                        .dot-out--3 { animation-delay: 0.3s; }
                                        @keyframes bounceOut { 0% { transform: translateY(0); opacity: 1; } 100% { transform: translateY(-10px); opacity: 0.4; } }
                                    `;
                                    document.head.appendChild(s);
                                }

                                let timerInterval;
                                Swal.fire({
                                    icon: 'warning',
                                    title: '<span class="fw-bold">Sedang Keluar...</span>',
                                    html: `
                                        <div class="text-muted mb-3">Mengakhiri sesi Anda...</div>
                                        <div class="my-6" style="display:flex;justify-content:center;gap:10px;">
                                            <div class="dot-out"></div>
                                            <div class="dot-out dot-out--2"></div>
                                            <div class="dot-out dot-out--3"></div>
                                        </div>
                                        <div class="progress bg-secondary mt-3" style="height:12px;border-radius:20px;overflow:hidden;">
                                            <div id="logout-progress-bar" class="progress-bar bg-danger" style="width:0%;border-radius:20px;"></div>
                                        </div>
                                        <div id="logout-percent" class="mt-2 fw-bold text-gray-700">0%</div>
                                    `,
                                    width: 400,
                                    padding: '2em',
                                    showConfirmButton: false,
                                    allowOutsideClick: false,
                                    timer: 1800,
                                    didOpen: () => {
                                        let bar = document.getElementById('logout-progress-bar');
                                        let pct = document.getElementById('logout-percent');
                                        let width = 0;
                                        timerInterval = setInterval(() => {
                                            width += Math.floor(Math.random() * 6) + 2;
                                            if (width > 100) width = 100;
                                            if (bar) bar.style.width = width + '%';
                                            if (pct) pct.innerHTML = width + '%';
                                            if (width >= 100) clearInterval(timerInterval);
                                        }, 50);
                                    },
                                    willClose: () => { clearInterval(timerInterval); }
                                }).then(() => {
                                    document.getElementById('logout-form').submit();
                                });
                            }
                        });
                    });
                </script>

            </div>
        </div>
    </div>
    <div class="flex-column-fluid px-4 px-lg-8 py-4" id="kt_app_sidebar_nav">
        <div id="kt_app_sidebar_nav_wrapper" class="d-flex flex-column hover-scroll-y pe-4 me-n4" data-kt-scroll="true"
            data-kt-scroll-activate="true" data-kt-scroll-height="auto"
            data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
            data-kt-scroll-wrappers="#kt_app_sidebar, #kt_app_sidebar_nav" data-kt-scroll-offset="5px">

            @if (($saMode ?? 'pos') === 'pos' || ! ($isSuperadminView ?? false))
            <div class="px-3 mb-6">
                <div class="d-flex align-items-center flex-column w-100 mb-6">
                    <div class="d-flex justify-content-between fw-bolder fs-6 text-gray-800 w-100 mt-auto mb-3">
                        {{-- Istilah target mengikuti vertical: laundry pakai "Profit". --}}
                        <span>{{ (($currentTenant ?? null) && $currentTenant->isLaundry()) ? 'Target Profit Hari Ini' : 'Target Penjualan Hari Ini' }}</span>
                        <span id="sb-target">Rp {{ number_format($salesTarget ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="w-100 bg-light-success rounded mb-2" style="height: 24px">
                        <div id="sb-progress" class="{{ $salesProgressColor ?? 'bg-warning' }} rounded" role="progressbar"
                            style="height: 24px; width: {{ $salesBarWidth ?? 0 }}%; transition: width 0.5s ease;"
                            aria-valuenow="{{ $salesBarWidth ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                    <div class="fw-semibold fs-7 text-muted w-100 mt-auto d-flex justify-content-between">
                        <span id="sb-percent">Tercapai {{ $salesPercentage ?? 0 }}%</span>
                        @if (($salesPercentage ?? 0) >= 100)
                            <span class="text-success fw-bold">Target Terlampaui! 🎉</span>
                        @else
                            <span class="text-warning fw-bold">Ayo Semangat! 💪</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="px-1 mb-6">
                <div class="border border-primary border-dashed bg-light-primary rounded w-100 py-3 px-4">
                    @if (!empty($isLaundryNow))
                        {{-- Laundry: yang dibandingkan dgn target = PROFIT (omzet - pengeluaran). --}}
                        <span class="fs-6 text-primary fw-bold">Profit Hari Ini</span>
                        <div id="sb-income" class="fs-2 fw-bold text-gray-800">Rp {{ number_format($achieved ?? 0, 0, ',', '.') }}</div>
                        <div class="fs-8 text-muted mt-1">Omzet Rp {{ number_format($income ?? 0, 0, ',', '.') }} − pengeluaran</div>
                    @else
                        <span class="fs-6 text-primary fw-bold">Penjualan Hari Ini</span>
                        <div id="sb-income" class="fs-2 fw-bold text-gray-800">Rp {{ number_format($income ?? 0, 0, ',', '.') }}</div>
                    @endif
                </div>
            </div>

            {{-- Plan Deposit: sisa saldo (hanya untuk tenant mode deposit) --}}
            @if (!empty($depositMode))
                @php $depLow = ($depositPoints ?? 0) <= ($depositWarningThreshold ?? 10000); @endphp
                <div class="px-1 mb-6">
                    <div class="border border-{{ $depLow ? 'danger' : 'success' }} border-dashed bg-light-{{ $depLow ? 'danger' : 'success' }} rounded w-100 py-3 px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-6 text-{{ $depLow ? 'danger' : 'success' }} fw-bold">Sisa Saldo Deposit</span>
                            @can('view_billing')
                                <a href="{{ route('deposit.index') }}" class="badge badge-light-primary">Top Up</a>
                            @endcan
                        </div>
                        <div id="sb-deposit-points" class="fs-2 fw-bold {{ $depLow ? 'text-danger' : 'text-gray-800' }}">Rp {{ number_format($depositPoints ?? 0, 0, ',', '.') }}</div>
                        <div class="fs-8 text-muted">Potongan Rp{{ number_format($depositFee ?? 169, 0, ',', '.') }} / transaksi</div>
                        @if ($depLow)
                            <div class="fs-8 text-danger fw-bold mt-1">Saldo menipis — segera top up!</div>
                        @elseif (!empty($depositExpiringSoon) && !empty($depositExpiresAt))
                            <div class="fs-8 text-danger fw-semibold mt-1">Hangus {{ \Carbon\Carbon::parse($depositExpiresAt)->translatedFormat('d M Y') }} bila tak dipakai.</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Pengeluaran Harian: ringkasan + tombol catat cepat (modal) --}}
            @can('view_expense')
                <div class="px-1 mb-6">
                    <div class="border border-danger border-dashed bg-light-danger rounded w-100 py-3 px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-6 text-danger fw-bold">Pengeluaran Hari Ini</span>
                            <button type="button" class="btn btn-sm btn-danger py-1 px-3" data-bs-toggle="modal" data-bs-target="#modal-quick-expense">
                                <i class="ki-outline ki-plus fs-5"></i> Catat
                            </button>
                        </div>
                        <div id="sb-expense-spent" class="fs-2 fw-bold text-gray-800">Rp {{ number_format($dailySpent ?? 0, 0, ',', '.') }}</div>
                        <div class="fs-8 text-muted mt-1">Total pengeluaran tanggal ini (gabungan semua shift).</div>
                    </div>
                </div>
            @endcan

            @php
                // Tenant LAUNDRY memakai kartu menu sendiri (Kasir Laundry, Produksi, Layanan,
                // Pelanggan); kartu khas F&B (Kasir F&B, Dapur, Menu F&B) disembunyikan.
                $sbIsLaundry = ($currentTenant ?? null) && $currentTenant->isLaundry();
            @endphp

            <div class="mb-6 mt-4">
                <h3 class="text-gray-800 fw-bold mb-8">Menu Utama</h3>
                <div class="row row-cols-3" data-kt-buttons="true" data-kt-buttons-target="[data-kt-button]">

                    @if ($sbIsLaundry)
                        {{-- ===== KARTU MENU LAUNDRY ===== --}}
                        @can('view_kasir')
                            <div class="col mb-4">
                                <a href="{{ route('laundry.kasir.index') }}"
                                    class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                                    data-kt-button="true">
                                    <span class="mb-2"><i class="ki-outline ki-handcart fs-2x text-primary"></i></span>
                                    <span class="fs-8 fw-bold">Kasir</span>
                                </a>
                            </div>
                        @endcan
                        @can('view_kasir')
                            <div class="col mb-4">
                                <a href="{{ route('shifts.index') }}"
                                    class="btn btn-icon btn-outline btn-bg-light btn-active-light-warning btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                                    data-kt-button="true">
                                    <span class="mb-2"><i class="ki-outline ki-time fs-2x text-warning"></i></span>
                                    <span class="fs-8 fw-bold">Shift</span>
                                </a>
                            </div>
                        @endcan
                        @can('view_kitchen')
                            <div class="col mb-4">
                                <a href="{{ route('laundry.produksi.index') }}"
                                    class="btn btn-icon btn-outline btn-bg-light btn-active-light-info btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                                    data-kt-button="true">
                                    <span class="mb-2"><i class="ki-outline ki-loading fs-2x text-info"></i></span>
                                    <span class="fs-8 fw-bold">Produksi</span>
                                </a>
                            </div>
                        @endcan
                        @can('view_data_master')
                            <div class="col mb-4">
                                <a href="{{ route('laundry.services.index') }}"
                                    class="btn btn-icon btn-outline btn-bg-light btn-active-light-success btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                                    data-kt-button="true">
                                    <span class="mb-2"><i class="ki-outline ki-abstract-26 fs-2x text-success"></i></span>
                                    <span class="fs-8 fw-bold">Layanan</span>
                                </a>
                            </div>
                            <div class="col mb-4">
                                <a href="{{ route('laundry.customers.index') }}"
                                    class="btn btn-icon btn-outline btn-bg-light btn-active-light-warning btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                                    data-kt-button="true">
                                    <span class="mb-2"><i class="ki-outline ki-profile-user fs-2x text-warning"></i></span>
                                    <span class="fs-8 fw-bold">Pelanggan</span>
                                </a>
                            </div>
                        @endcan
                    @else
                    {{-- KASIR: Superadmin + admin + kasir --}}
                    @can('view_kasir')
                    <div class="col mb-4">
                        <a href="{{ route('kasir.index') }}"
                            class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                            data-kt-button="true">
                            <span class="mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"
                                    fill="currentColor" class="text-gray-700" viewBox="0 0 16 16">
                                    <path
                                        d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35l2.609 3.044A1.5 1.5 0 0 1 16 5.37v.255a2.375 2.375 0 0 1-4.25 1.458A2.37 2.37 0 0 1 9.875 8 2.37 2.37 0 0 1 8 7.083 2.37 2.37 0 0 1 6.125 8a2.37 2.37 0 0 1-1.875-.917A2.375 2.375 0 0 1 0 5.625V5.37a1.5 1.5 0 0 1 .361-.976zm1.78 4.275a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 1 0 2.75 0V5.37a.5.5 0 0 0-.12-.325L12.27 2H3.73L1.12 5.045A.5.5 0 0 0 1 5.37v.255a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0M1.5 8.5A.5.5 0 0 1 2 9v6h1v-5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v5h6V9a.5.5 0 0 1 1 0v6h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1V9a.5.5 0 0 1 .5-.5M4 15h3v-5H4zm5-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1zm3 0h-2v3h2z" />
                                </svg>
                            </span>
                            <span class="fs-7 fw-bold">Kasir</span>
                        </a>
                    </div>

                    <div class="col mb-4">
                        <a href="{{ route('shifts.index') }}"
                            class="btn btn-icon btn-outline btn-bg-light btn-active-light-warning btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                            data-kt-button="true">
                            <span class="mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28"
                                    fill="currentColor" class="text-warning" viewBox="0 0 16 16">
                                    <path
                                        d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432z" />
                                    <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z" />
                                    <path
                                        d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5" />
                                </svg>
                            </span>
                            <span class="fs-7 fw-bold">Shift</span>
                        </a>
                    </div>
                    @endcan

                    {{-- KITCHEN: Superadmin + admin + kasir + kitchen --}}
                    @can('view_kitchen')
                    <div class="col mb-4">
                        <a href="{{ route('kitchen.index') }}"
                            class="btn btn-icon btn-outline btn-bg-light btn-active-light-danger btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200"
                            data-kt-button="true">
                            <span class="mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"
                                    fill="currentColor" class="text-danger" viewBox="0 0 16 16">
                                    <path
                                        d="M8 16c3.314 0 6-2 6-5.5 0-1.5-.5-2.8-1.5-3.8l-1.3-1.3c-.498-.498-.9-1.083-1.181-1.734C8.804 1.25 8.99 0 8.99 0s-.5.3-1.3.8c-.8.5-1.4 1.2-1.9 2.1-.5.9-.8 1.9-.8 3.1 0 1.2.3 2.1.8 3.1.2.5.4 1 .7 1.4-.4-.5-.9-1.2-1.2-2C4.5 7.6 4 6.7 4 5.9c0-.4.1-.9.2-1.4C2.5 5.8 1.5 7.7 1.5 10 1.5 13 4.186 16 8 16m0-1c-2.761 0-5-1.79-5-4 0-.8.2-1.5.5-2.2.3-.6.7-1.2 1.2-1.7.5-.5 1-1.1 1.4-1.8.3-.5.6-1.1.8-1.7.2.6.5 1.1.8 1.7.4.7.9 1.3 1.4 1.8.5.5.9 1.1 1.2 1.7.3.7.5 1.4.5 2.2 0 2.21-2.239 4-5 4" />
                                </svg>
                            </span>
                            <span class="fs-7 fw-bold">Dapur</span>
                        </a>
                    </div>
                    @endcan

                    {{-- MENU MAKANAN & MINUMAN (data master) --}}
                    @can('view_data_master')
                    <div class="col mb-4">
                        <a href="{{ route('menus.index') }}"
                            class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200">
                            <span class="mb-2"><i class="ki-outline ki-coffee fs-2x text-primary"></i></span>
                            <span class="fs-7 fw-bold">Menu F&B</span>
                        </a>
                    </div>
                    @endcan
                    @endif {{-- /vertical: laundry vs F&B --}}

    {{-- HPP & INVENTORY: F&B, dari paket (Customize) ATAU dibeli terpisah sebagai
         add-on. Dipakai Addon::bolehLihat, bukan Plan::tenantAllows, karena
         add-on boleh dibatasi ke peran tertentu — mis. hanya pemilik toko —
         sementara fiturnya sendiri tetap bekerja untuk semua transaksi. --}}
                    @php
                        $sbHpp = auth()->user()->isSuperadmin()
                            || (\App\Tenancy\Plan::tenantAllows($currentTenant ?? null, 'inventory_hpp')
                                && \App\Tenancy\Addon::bolehLihat($currentTenant ?? null, 'inventory_hpp'));
                    @endphp
                    @if (! $sbIsLaundry && $sbHpp)
                        @can('view_data_master')
                            <div class="col mb-4">
                                <a href="{{ route('fnb.stock.index') }}"
                                    class="btn btn-icon btn-outline btn-bg-light btn-active-light-warning btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200">
                                    <span class="mb-2"><i class="ki-outline ki-package fs-2x text-warning"></i></span>
                                    <span class="fs-8 fw-bold">Stok</span>
                                </a>
                            </div>
                            <div class="col mb-4">
                                <a href="{{ route('fnb.recipes.index') }}"
                                    class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200">
                                    <span class="mb-2"><i class="ki-outline ki-chart-pie-simple fs-2x text-primary"></i></span>
                                    <span class="fs-8 fw-bold">HPP / Resep</span>
                                </a>
                            </div>
                        @endcan
                    @endif

                    {{-- KARYAWAN (User Management) --}}
                    @can('view_resources')
                    <div class="col mb-4">
                        <a href="{{ route('users.index') }}"
                            class="btn btn-icon btn-outline btn-bg-light btn-active-light-info btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200">
                            <span class="mb-2"><i class="ki-outline ki-people fs-2x text-info"></i></span>
                            <span class="fs-7 fw-bold">Karyawan</span>
                        </a>
                    </div>
                    @endcan

                    {{-- PROGRAM AFFILIATE: owner tenant (gabung + dashboard referral) --}}
                    @can('affiliate.refer')
                    <div class="col mb-4">
                        <a href="{{ route('affiliate.my') }}"
                            class="btn btn-icon btn-outline btn-bg-light btn-active-light-success btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200">
                            <span class="mb-2"><i class="ki-outline ki-share fs-2x text-success"></i></span>
                            <span class="fs-7 fw-bold">Affiliate</span>
                        </a>
                    </div>
                    @endcan

                </div>{{-- END .row --}}
            </div>{{-- END .mb-6 --}}

            {{-- ================= FITUR MENDATANG (terkunci) — hanya OWNER =================
                 Daftar mengacu ke Fitur_POS_Full_Service_Mooda.xlsx: fitur yang BELUM ada
                 ditampilkan sebagai ikon terkunci supaya owner tahu roadmap produk. --}}
            @if (auth()->user()->hasRole('owner'))
                @php
                    /**
                     * Daftar fitur TERKUNCI.
                     * Aturan: 'module' = null  -> fitur belum dibangun (selalu "segera").
                     *         'module' = 'x'   -> fitur SUDAH ada, tapi hanya untuk paket tertentu;
                     *                             ikon terkunci muncul HANYA bila paket tenant belum punya
                     *                             (kalau punya, fitur tampil sebagai menu nyata di atas).
                     */
                    $aiFeatures = [
                        ['label' => 'AI Assistant', 'icon' => 'ki-messages',      'color' => 'success', 'module' => null, 'desc' => 'Chatbot WhatsApp & upselling otomatis'],
                        ['label' => 'AI Prediksi',  'icon' => 'ki-chart-line-up', 'color' => 'primary', 'module' => null, 'desc' => 'Prediksi stok, penjualan & rekomendasi promo'],
                    ];

                    // Laundry: hanya fitur AI (HPP/Inventory dsb. modul F&B).
                    $candidateLocked = $sbIsLaundry ? $aiFeatures : array_merge([
                        ['label' => 'HPP',        'icon' => 'ki-chart-pie-simple', 'color' => 'primary', 'module' => 'inventory_hpp', 'desc' => 'HPP per menu, margin & analisis food cost — paket Customize'],
                        ['label' => 'Inventory',  'icon' => 'ki-package',          'color' => 'warning', 'module' => 'inventory_hpp', 'desc' => 'Stok berbasis lot (FIFO/FEFO), opname & kartu stok — paket Customize'],
                        // QR Menu: modulnya BELUM dibangun (tidak ada route/controller/tabel),
                        // 'qr_selforder' baru tercatat di config/plans.php -> tetap "segera", bukan "terkunci paket".
                        ['label' => 'QR Menu',    'icon' => 'ki-scan-barcode',     'color' => 'success', 'module' => null,            'desc' => 'QR menu, self ordering & pesan dari meja'],
                        ['label' => 'CRM',        'icon' => 'ki-profile-circle',   'color' => 'info',    'module' => null,            'desc' => 'Database pelanggan, membership & poin loyalitas'],
                        ['label' => 'Akuntansi',  'icon' => 'ki-bill',             'color' => 'dark',    'module' => null,            'desc' => 'Laba rugi, arus kas & hutang piutang'],
                    ], $aiFeatures);

                    // Sembunyikan yang SUDAH termasuk paket tenant (atau Superadmin) — itu sudah jadi menu nyata.
                    $sbIsSa = auth()->user()->isSuperadmin();
                    $lockedFeatures = collect($candidateLocked)->reject(function ($f) use ($sbIsSa, $currentTenant) {
                        if (empty($f['module'])) {
                            return false; // belum dibangun -> tetap terkunci
                        }
                        return $sbIsSa || \App\Tenancy\Plan::tenantAllows($currentTenant ?? null, $f['module']);
                    })->values();

                    /**
                     * Dua kelompok berbeda, jangan dicampur:
                     * - planLocked  : modul SUDAH ada (mis. HPP, Inventory), hanya belum masuk paket tenant
                     *                 -> ajakan upgrade, bukan "segera".
                     * - soonFeatures: modul memang belum dibangun -> "segera".
                     */
                    $planLocked   = $lockedFeatures->filter(fn ($f) => ! empty($f['module']))->values()->all();
                    $soonFeatures = $lockedFeatures->filter(fn ($f) => empty($f['module']))->values()->all();

                    $lockedGroups = [
                        [
                            'items'  => $planLocked,
                            'title'  => 'Fitur Terkunci',
                            'badge'  => 'Upgrade Paket',
                            'style'  => 'warning',
                            'note'   => 'Fitur ini <b>sudah tersedia</b> di Mooda, tapi belum termasuk paket Anda. Upgrade paket untuk membukanya.',
                            'suffix' => 'belum termasuk paket Anda',
                        ],
                        [
                            'items'  => $soonFeatures,
                            'title'  => 'Fitur Mendatang',
                            'badge'  => 'Segera',
                            'style'  => 'secondary',
                            'note'   => 'Sedang dikembangkan dan akan hadir di pembaruan berikutnya.',
                            'suffix' => 'belum tersedia',
                        ],
                    ];
                @endphp
                @foreach ($lockedGroups as $g)
                    @continue (! count($g['items']))
                    <div class="mb-6">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h3 class="text-gray-800 fw-bold mb-0 fs-5">{{ $g['title'] }}</h3>
                            <span class="badge badge-light-{{ $g['style'] }} fs-9">{{ $g['badge'] }}</span>
                        </div>
                        <div class="row row-cols-3 g-3 mb-2">
                            @foreach ($g['items'] as $f)
                                <div class="col mb-4">
                                    <span class="btn btn-icon btn-outline btn-bg-light btn-flex flex-column flex-center w-lg-90px h-lg-90px w-70px h-70px border-gray-200 position-relative opacity-75"
                                        style="cursor:not-allowed"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ $f['label'] }} — {{ $f['desc'] }} ({{ $g['suffix'] }})">
                                        <span class="mb-2"><i class="ki-outline {{ $f['icon'] }} fs-2x text-{{ $f['color'] }}"></i></span>
                                        <span class="fs-8 fw-bold text-gray-600">{{ $f['label'] }}</span>
                                        <span class="position-absolute translate-middle badge badge-circle badge-{{ $g['style'] }}"
                                            style="top:8px;left:calc(100% - 10px);width:20px;height:20px">
                                            <i class="ki-solid ki-lock-2 fs-9 text-gray-700"></i>
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-muted fs-8 px-1">{!! $g['note'] !!}</div>
                    </div>
                @endforeach
            @endif
            @else
            {{-- Sidebar Superadmin (mode Analitik): pintasan platform --}}
            <div class="mb-6 mt-4">
                <h3 class="text-gray-800 fw-bold mb-6">Platform</h3>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('platform-menu.index') }}" class="btn btn-flex btn-primary fw-bold justify-content-start">
                        <i class="ki-outline ki-element-11 fs-3 me-2"></i> Platform Menu
                    </a>
                    <a href="{{ route('tenants.index') }}" class="btn btn-flex btn-light-primary fw-bold justify-content-start">
                        <i class="ki-outline ki-abstract-26 fs-3 me-2"></i> Manajemen Tenant
                    </a>
                    <a href="{{ route('deposit-settings.index') }}" class="btn btn-flex btn-light-warning fw-bold justify-content-start">
                        <i class="ki-outline ki-wallet fs-3 me-2"></i> Setelan Deposit
                    </a>
                    <a href="{{ route('plan-settings.index') }}" class="btn btn-flex btn-light-info fw-bold justify-content-start">
                        <i class="ki-outline ki-price-tag fs-3 me-2"></i> Setelan Paket
                    </a>
                    <a href="{{ route('founders.index') }}" class="btn btn-flex btn-light fw-bold justify-content-start">
                        <i class="ki-outline ki-people fs-3 me-2"></i> Tentang Kami (Founder)
                    </a>
                    @can('blog.manage')
                        <a href="{{ route('blog.admin.posts.index') }}" class="btn btn-flex btn-light-info fw-bold justify-content-start">
                            <i class="ki-outline ki-notepad-edit fs-3 me-2"></i> Kelola Blog
                        </a>
                        <a href="{{ route('blog.admin.categories.index') }}" class="btn btn-flex btn-light fw-bold justify-content-start">
                            <i class="ki-outline ki-category fs-3 me-2"></i> Kategori Blog
                        </a>
                    @endcan
                    @can('affiliate.manage')
                        <a href="{{ route('affiliates.index') }}" class="btn btn-flex btn-light-success fw-bold justify-content-start">
                            <i class="ki-outline ki-share fs-3 me-2"></i> Affiliate
                        </a>
                        <a href="{{ route('affiliates.withdrawals') }}" class="btn btn-flex btn-light fw-bold justify-content-start">
                            <i class="ki-outline ki-dollar fs-3 me-2"></i> Pencairan Komisi
                        </a>
                        <a href="{{ route('affiliates.settings') }}" class="btn btn-flex btn-light fw-bold justify-content-start">
                            <i class="ki-outline ki-setting-3 fs-3 me-2"></i> Setelan Affiliate
                        </a>
                    @endcan
                    @can('view_resources')
                        <a href="{{ route('users.index') }}" class="btn btn-flex btn-light fw-bold justify-content-start">
                            <i class="ki-outline ki-people fs-3 me-2"></i> User Management
                        </a>
                    @endcan
                    @can('view_help')
                        <a href="{{ route('log-activity.index') }}" class="btn btn-flex btn-light fw-bold justify-content-start">
                            <i class="ki-outline ki-time fs-3 me-2"></i> Log Activity
                        </a>
                    @endcan
                </div>
            </div>
            @endif
        </div>
    </div>
    <div class="flex-column-auto d-flex flex-center px-4 px-lg-8 py-3 py-lg-8" id="kt_app_sidebar_footer">
        <div class="app-footer-item">
            <a href="{{ route('settings.index') }}"
                class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px w-md-40px h-md-40px">
                <i class="ki-outline ki-setting-2 fs-2"></i>
            </a>
        </div>
    </div>
</div>
