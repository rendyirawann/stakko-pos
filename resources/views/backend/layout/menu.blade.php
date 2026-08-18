<div class="app-header-menu app-header-mobile-drawer align-items-start align-items-lg-center w-100" data-kt-drawer="true"
    data-kt-drawer-name="app-header-menu" data-kt-drawer-activate="{default: true, lg: false}"
    data-kt-drawer-overlay="true" data-kt-drawer-width="250px" data-kt-drawer-direction="end"
    data-kt-drawer-toggle="#kt_app_header_menu_toggle" data-kt-swapper="true"
    data-kt-swapper-mode="{default: 'append', lg: 'prepend'}"
    data-kt-swapper-parent="{default: '#kt_app_body', lg: '#kt_app_header_wrapper'}">
    <div class="menu menu-rounded menu-active-bg menu-state-primary menu-column menu-lg-row menu-title-gray-700 menu-icon-gray-500 menu-arrow-gray-500 menu-bullet-gray-500 my-5 my-lg-0 align-items-stretch fw-semibold px-2 px-lg-0"
        id="kt_app_header_menu" data-kt-menu="true">
        {{-- PLATFORM MENU (Superadmin) — paling depan --}}
        @can('view_tenants')
            <div class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('platform-menu.*') ? 'here show ' : '' }}">
                <a href="{{ route('platform-menu.index') }}"
                    class="menu-link px-4 {{ request()->routeIs('platform-menu.*') ? 'active ' : '' }}">
                    <span class="menu-title">Platform Menu</span>
                </a>
            </div>
        @endcan

        <div
            class="menu-item menu-here-bg me-0 me-lg-2 menu-hover-bg menu-hover-bg-warning {{ request()->routeIs('dashboard') ? 'here show ' : '' }}">
            <a href="{{ route('dashboard') }}"
                class="menu-link px-4 {{ request()->routeIs('dashboard') ? 'active ' : '' }}">

                <span class="menu-title">Dashboards</span>
            </a>
        </div>

        {{-- ===== MENU LAUNDRY (hanya tenant vertical 'laundry') ===== --}}
        @if (($currentTenant ?? null) && $currentTenant->isLaundry())
            @can('view_kasir')
                <div class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('laundry.kasir.*') ? 'here show ' : '' }}">
                    <a href="{{ route('laundry.kasir.index') }}" class="menu-link px-4 {{ request()->routeIs('laundry.kasir.*') ? 'active ' : '' }}">
                        <span class="menu-title">Kasir Laundry</span></a>
                </div>
            @endcan
            @can('view_kitchen')
                <div class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('laundry.produksi.*') ? 'here show ' : '' }}">
                    <a href="{{ route('laundry.produksi.index') }}" class="menu-link px-4 {{ request()->routeIs('laundry.produksi.*') ? 'active ' : '' }}">
                        <span class="menu-title">Produksi</span></a>
                </div>
            @endcan
            @can('view_data_master')
                <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
                    class="menu-item menu-lg-down-accordion menu-sub-lg-down-indention me-0 me-lg-2">
                    <span class="menu-link px-4 {{ request()->routeIs('laundry.services.*', 'laundry.customers.*') ? 'active ' : '' }}">
                        <span class="menu-title">Data Master</span><span class="menu-arrow d-lg-none"></span></span>
                    <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown px-lg-2 py-lg-4 w-lg-210px">
                        <div class="menu-item {{ request()->routeIs('laundry.services.*') ? 'here show ' : '' }}">
                            <a class="menu-link py-3" href="{{ route('laundry.services.index') }}">
                                <span class="menu-icon"><i class="ki-outline ki-abstract-26 fs-2"></i></span><span class="menu-title">Layanan</span></a></div>
                        <div class="menu-item {{ request()->routeIs('laundry.customers.*') ? 'here show ' : '' }}">
                            <a class="menu-link py-3" href="{{ route('laundry.customers.index') }}">
                                <span class="menu-icon"><i class="ki-outline ki-people fs-2"></i></span><span class="menu-title">Pelanggan</span></a></div>
                    </div>
                </div>
            @endcan
        @endif

        {{-- DATA MASTER F&B (disembunyikan utk Superadmin di mode Analitik & untuk vertical laundry) --}}
        @if ((! ($isSuperadminView ?? false) || ($saMode ?? 'pos') === 'pos') && ! (($currentTenant ?? null) && $currentTenant->isLaundry()))
        @can('view_data_master')
            <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
                class="menu-item menu-lg-down-accordion menu-sub-lg-down-indention me-0 me-lg-2">
                <span
                    class="menu-link py-3  {{ request()->routeIs('categories.index', 'menus.index', 'promos.index') ? 'active ' : '' }}">
                    <span class="menu-title">Data Master</span>
                    <span class="menu-arrow d-lg-none">
                    </span>
                </span>
                <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown px-lg-2 py-lg-4 w-lg-210px">

                    <div class="menu-item {{ request()->routeIs('menus.index') ? 'here show ' : '' }}">
                        <a class="menu-link py-3" href="{{ route('menus.index') }}"> <span class="menu-icon"><i
                                    class="ki-outline ki-coffee fs-2"></i></span>
                            <span class="menu-title">Menu Makanan & Minuman</span>
                        </a>
                    </div>
                    @if (\App\Tenancy\Plan::tenantAllows($currentTenant ?? null, 'promo'))
                        <div class="menu-item {{ request()->routeIs('promos.index') ? 'here show ' : '' }}">
                            <a class="menu-link py-3" href="{{ route('promos.index') }}">
                                <span class="menu-icon"><i class="ki-outline ki-discount fs-2"></i></span>
                                <span class="menu-title">Promo & Diskon</span>
                            </a>
                        </div>
                    @endif
                    @if (\App\Tenancy\Plan::tenantAllows($currentTenant ?? null, 'tables'))
                        <div class="menu-item {{ request()->routeIs('tables.index') ? 'here show ' : '' }}">
                            <a class="menu-link py-3" href="{{ route('tables.index') }}">
                                <span class="menu-icon"><i class="ki-outline ki-tablet-book fs-2"></i></span>
                                <span class="menu-title">Manajemen Meja</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endcan
        @endif

        {{-- REPORT (disembunyikan utk Superadmin di mode Analitik) --}}
        @if (! ($isSuperadminView ?? false) || ($saMode ?? 'pos') === 'pos')
        @can('view_report')
            <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
                class="menu-item menu-lg-down-accordion menu-sub-lg-down-indention me-0 me-lg-2">
                <span
                    class="menu-link py-3  {{ request()->routeIs('reports.sales.index', 'reports.items.index') ? 'active ' : '' }}">
                    <span class="menu-title">Report</span>
                    <span class="menu-arrow d-lg-none">
                    </span>
                </span>
                <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown px-lg-2 py-lg-4 w-lg-210px">

                    <div class="menu-item {{ request()->routeIs('reports.sales.index') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 " href="{{ route('reports.sales.index') }}"> <span class="menu-icon">
                                <i class="ki-outline ki-rocket fs-2"></i>
                            </span>
                            <span class="menu-title">Sales Report</span>
                        </a>
                    </div>
                    @if (\App\Tenancy\Plan::tenantAllows($currentTenant ?? null, 'report_items'))
                        <div class="menu-item {{ request()->routeIs('reports.items.index') ? 'here show ' : '' }}">
                            <a class="menu-link py-3 " href="{{ route('reports.items.index') }}"> <span class="menu-icon">
                                    <i class="ki-outline ki-rocket fs-2"></i>
                                </span>
                                <span class="menu-title">Sales Items Report</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endcan
        @endif

        {{-- PENGELUARAN: owner/admin/kasir tenant (Superadmin tanpa tenant tidak tampil) --}}
        @if (auth()->user()->tenant_id)
            @can('view_expense')
                <div class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('expenses.*') ? 'here show ' : '' }}">
                    <a href="{{ route('expenses.index') }}" class="menu-link px-4 {{ request()->routeIs('expenses.*') ? 'active ' : '' }}">
                        <span class="menu-title">Pengeluaran</span>
                    </a>
                </div>
            @endcan
        @endif

        {{-- RESOURCES: Superadmin only --}}
        @can('view_resources')
            <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
                class="menu-item menu-lg-down-accordion menu-sub-lg-down-indention me-0 me-lg-2">
                <span class="menu-link py-3  {{ request()->routeIs('users.index', 'roles.index') ? 'active ' : '' }}">
                    <span class="menu-title">Resources</span>
                    <span class="menu-arrow d-lg-none">
                    </span>
                </span>
                <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown px-lg-2 py-lg-4 w-lg-210px">

                    <div class="menu-item {{ request()->routeIs('users.index') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 " href="{{ route('users.index') }}">
                            <span class="menu-icon">
                                <i class="ki-outline ki-rocket fs-2"></i>
                            </span>
                            <span class="menu-title">User Management</span>
                        </a>
                    </div>
                    @hasrole('Superadmin')
                        <div class="menu-item {{ request()->routeIs('roles.index') ? 'here show ' : '' }}">
                            <a class="menu-link py-3" href="{{ route('roles.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-code fs-2"></i>
                                </span>
                                <span class="menu-title">Role Management</span>
                            </a>
                        </div>
                    @endhasrole
                </div>
            </div>
        @endcan


        {{-- MANAJEMEN TENANT: Superadmin only --}}
        @can('view_tenants')
            <div
                class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('tenants.*') ? 'here show ' : '' }}">
                <a href="{{ route('tenants.index') }}"
                    class="menu-link px-4 {{ request()->routeIs('tenants.*') ? 'active ' : '' }}">
                    <span class="menu-title">Manajemen Tenant</span>
                </a>
            </div>

            {{-- MANAJEMEN SHIFT: koreksi modal & uang aktual (Superadmin only) --}}
            <div class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('superadmin.shifts.*') ? 'here show ' : '' }}">
                <a href="{{ route('superadmin.shifts.index') }}"
                    class="menu-link px-4 {{ request()->routeIs('superadmin.shifts.*') ? 'active ' : '' }}">
                    <span class="menu-title">Manajemen Shift</span>
                </a>
            </div>

            {{-- AKUN DEMO: Superadmin only --}}
            <div class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('demo-accounts.*') ? 'here show ' : '' }}">
                <a href="{{ route('demo-accounts.index') }}"
                    class="menu-link px-4 {{ request()->routeIs('demo-accounts.*') ? 'active ' : '' }}">
                    <span class="menu-title">Akun Demo</span>
                </a>
            </div>

            {{-- PAYMENT (dropdown): Setelan Deposit + Channel VA DOKU — Superadmin only --}}
            @php($payActive = request()->routeIs('payment-gateway.*') || request()->routeIs('deposit-settings.*') || request()->routeIs('doku-channels.*') || request()->routeIs('tripay-channels.*'))
            <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
                class="menu-item menu-lg-down-accordion menu-sub-lg-down-indention menu-here-bg me-0 me-lg-2 {{ $payActive ? 'here show ' : '' }}">
                <span class="menu-link px-4 {{ $payActive ? 'active ' : '' }}">
                    <span class="menu-title">Payment</span>
                    <span class="menu-arrow d-lg-none"></span>
                </span>
                <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown px-lg-2 py-lg-4 w-lg-210px">
                    <div class="menu-item {{ request()->routeIs('payment-gateway.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('payment-gateway.*') ? 'active ' : '' }}" href="{{ route('payment-gateway.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-credit-cart fs-2"></i></span>
                            <span class="menu-title">Payment Gateway</span>
                        </a>
                    </div>
                    <div class="menu-item {{ request()->routeIs('deposit-settings.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('deposit-settings.*') ? 'active ' : '' }}" href="{{ route('deposit-settings.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-wallet fs-2"></i></span>
                            <span class="menu-title">Setelan Deposit</span>
                        </a>
                    </div>
                    <div class="menu-item {{ request()->routeIs('doku-channels.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('doku-channels.*') ? 'active ' : '' }}" href="{{ route('doku-channels.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-bank fs-2"></i></span>
                            <span class="menu-title">Channel VA DOKU</span>
                        </a>
                    </div>
                    <div class="menu-item {{ request()->routeIs('tripay-channels.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('tripay-channels.*') ? 'active ' : '' }}" href="{{ route('tripay-channels.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-abstract-26 fs-2"></i></span>
                            <span class="menu-title">Channel Tripay</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- SITUS (dropdown): Kelola Situs + FAQ + Logo Partner + Mode Pemeliharaan — Superadmin only --}}
            @php($situsActive = request()->routeIs('site-content.*') || request()->routeIs('faqs.*') || request()->routeIs('social-links.*') || request()->routeIs('partner-logos.*') || request()->routeIs('maintenance-settings.*'))
            <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
                class="menu-item menu-lg-down-accordion menu-sub-lg-down-indention menu-here-bg me-0 me-lg-2 {{ $situsActive ? 'here show ' : '' }}">
                <span class="menu-link px-4 {{ $situsActive ? 'active ' : '' }}">
                    <span class="menu-title">Situs</span>
                    <span class="menu-arrow d-lg-none"></span>
                </span>
                <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown px-lg-2 py-lg-4 w-lg-210px">
                    <div class="menu-item {{ request()->routeIs('site-content.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('site-content.*') ? 'active ' : '' }}" href="{{ route('site-content.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-global fs-2"></i></span>
                            <span class="menu-title">Kelola Situs</span>
                        </a>
                    </div>
                    <div class="menu-item {{ request()->routeIs('faqs.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('faqs.*') ? 'active ' : '' }}" href="{{ route('faqs.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-questionnaire-tablet fs-2"></i></span>
                            <span class="menu-title">FAQ Landing</span>
                        </a>
                    </div>
                    <div class="menu-item {{ request()->routeIs('social-links.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('social-links.*') ? 'active ' : '' }}" href="{{ route('social-links.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-instagram fs-2"></i></span>
                            <span class="menu-title">Sosial Media</span>
                        </a>
                    </div>
                    <div class="menu-item {{ request()->routeIs('partner-logos.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('partner-logos.*') ? 'active ' : '' }}" href="{{ route('partner-logos.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-picture fs-2"></i></span>
                            <span class="menu-title">Logo Partner</span>
                        </a>
                    </div>
                    <div class="menu-item {{ request()->routeIs('maintenance-settings.*') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 {{ request()->routeIs('maintenance-settings.*') ? 'active ' : '' }}" href="{{ route('maintenance-settings.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-setting-2 fs-2"></i></span>
                            <span class="menu-title">Mode Pemeliharaan</span>
                        </a>
                    </div>
                </div>
            </div>
        @endcan

        {{-- LANGGANAN / BILLING: owner & admin tenant (bukan Superadmin yang tanpa tenant) --}}
        @if (auth()->user()->tenant_id && auth()->user()->can('view_billing'))
            <div
                class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('billing.*') ? 'here show ' : '' }}">
                <a href="{{ route('billing.index') }}"
                    class="menu-link px-4 {{ request()->routeIs('billing.*') ? 'active ' : '' }}">
                    <span class="menu-title">Langganan</span>
                </a>
            </div>

            {{-- PLAN DEPOSIT / POIN --}}
            <div
                class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('deposit.*') ? 'here show ' : '' }}">
                <a href="{{ route('deposit.index') }}"
                    class="menu-link px-4 {{ request()->routeIs('deposit.*') ? 'active ' : '' }}">
                    <span class="menu-title">Deposit</span>
                </a>
            </div>
        @endif

        {{-- APLIKASI TABLET (semua user login) --}}
        <div class="menu-item menu-here-bg me-0 me-lg-2 {{ request()->routeIs('download-app') ? 'here show ' : '' }}">
            <a href="{{ route('download-app') }}"
                class="menu-link px-4 {{ request()->routeIs('download-app') ? 'active ' : '' }}">
                <span class="menu-title">Aplikasi</span>
            </a>
        </div>

        {{-- HELP: Superadmin + admin + owner (yang punya view_help) --}}
        @can('view_help')
            <div data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start"
                class="menu-item menu-lg-down-accordion menu-sub-lg-down-indention me-0 me-lg-2">
                <span class="menu-link py-3  {{ request()->routeIs('log-activity.index') ? 'active ' : '' }}">
                    <span class="menu-title">Help</span>
                    <span class="menu-arrow d-lg-none">
                    </span>
                </span>
                <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown px-lg-2 py-lg-4 w-lg-200px">
                    <div class="menu-item {{ request()->routeIs('log-activity.index') ? 'here show ' : '' }}">
                        <a class="menu-link py-3 " href="{{ route('log-activity.index') }}">
                            <span class="menu-icon">
                                <i class="ki-outline ki-rocket fs-2"></i>
                            </span>
                            <span class="menu-title">Log Activity</span>
                        </a>
                    </div>
                </div>
            </div>
        @endcan

        {{-- TOGGLE MODE (Superadmin): Analitik <-> Kasir + pemilih toko utk mode POS --}}
        @if ($isSuperadminView ?? false)
            <div class="menu-item d-flex align-items-center ms-lg-2 my-2 my-lg-0 gap-2 flex-wrap">
                @if (($saMode ?? 'analytics') === 'pos' && !empty($saTenants) && count($saTenants))
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light-warning fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ki-outline ki-shop fs-5 me-1"></i> Toko: {{ $saPosTenantName ?? 'Pilih' }}
                        </button>
                        <div class="dropdown-menu p-2" style="max-height: 320px; overflow-y: auto;">
                            <div class="text-muted fw-semibold fs-8 px-3 pb-2">Operasikan kasir untuk toko:</div>
                            @foreach ($saTenants as $t)
                                <a class="dropdown-item rounded {{ ($saPosTenantId == $t->id) ? 'active' : '' }}"
                                    href="{{ route('pos-tenant.set', $t->id) }}">{{ $t->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (($saMode ?? 'analytics') === 'analytics')
                    <a href="{{ route('view-mode.switch', 'pos') }}" class="btn btn-sm btn-light-primary fw-bold">
                        <i class="ki-outline ki-handcart fs-4 me-1"></i> Mode Kasir
                    </a>
                @else
                    <a href="{{ route('view-mode.switch', 'analytics') }}" class="btn btn-sm btn-light-primary fw-bold">
                        <i class="ki-outline ki-chart-simple fs-4 me-1"></i> Mode Analitik
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
