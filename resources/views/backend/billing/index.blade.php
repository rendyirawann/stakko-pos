@extends('backend.layout.app')
@section('title', 'Langganan')
@section('content')

    <div id="kt_app_content" class="app-content flex-column-fluid mt-5">
        <div id="kt_app_content_container" class="app-container container-xxl">

            {{-- STATUS LANGGANAN --}}
            <div class="card card-flush mb-8">
                <div class="card-header pt-5">
                    <h3 class="card-title fw-bold text-gray-800 fs-2">Status Langganan</h3>
                </div>
                <div class="card-body">
                    @php
                        $active = $tenant->hasActiveAccess();
                        $statusMap = [
                            'active'   => ['Aktif', 'success'],
                            'trial'    => ['Trial', 'info'],
                            'expired'  => ['Kedaluwarsa', 'danger'],
                            'inactive' => ['Belum Aktif', 'warning'],
                        ];
                        [$statusLabel, $statusColor] = $statusMap[$tenant->subscription_status] ?? ['-', 'secondary'];
                    @endphp

                    <div class="row g-5">
                        <div class="col-md-3">
                            <div class="fs-7 text-muted">Bisnis</div>
                            <div class="fs-4 fw-bold text-gray-800">{{ $tenant->name }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-7 text-muted">Paket</div>
                            <div class="fs-4 fw-bold text-gray-800">{{ $tenant->plan ? ($plans[$tenant->plan]['name'] ?? ucfirst($tenant->plan)) : '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-7 text-muted">Status</div>
                            <span class="badge badge-light-{{ $statusColor }} fs-6 fw-bold">{{ $statusLabel }}</span>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-7 text-muted">Masa aktif s/d</div>
                            <div class="fs-4 fw-bold text-gray-800">
                                {{ $tenant->subscription_ends_at ? $tenant->subscription_ends_at->translatedFormat('d M Y') : '—' }}
                            </div>
                        </div>
                    </div>

                    @unless ($active)
                        <div class="alert alert-warning d-flex align-items-center mt-6 mb-0">
                            <i class="ki-outline ki-information-5 fs-2x text-warning me-4"></i>
                            <div>
                                <h4 class="mb-1 text-gray-900">Sistem belum aktif</h4>
                                <span class="text-gray-700">Pilih paket di bawah & selesaikan pembayaran untuk membuka semua fitur.</span>
                            </div>
                        </div>
                    @endunless
                </div>
            </div>

            {{-- PILIHAN PAKET (presisi di tengah) --}}
            <div class="row g-6 mb-8 justify-content-center">

                {{-- STARTER (Deposit / bayar sesuai pakai) — mengarah ke halaman Deposit --}}
                <div class="col-md-6 col-lg-5">
                    <div class="card card-flush h-100 border border-2 {{ $tenant->isDepositMode() ? 'border-success' : 'border-info' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h2 class="fw-bolder text-gray-900">Starter</h2>
                                    <div class="text-muted fs-7">Bayar sesuai pakai — tanpa langganan bulanan.</div>
                                </div>
                                @if ($tenant->isDepositMode())
                                    <span class="badge badge-success">Paket Anda</span>
                                @else
                                    <span class="badge badge-light-info">Hemat</span>
                                @endif
                            </div>

                            <div class="my-5">
                                <span class="fs-3x fw-bolder text-gray-900">Deposit</span>
                                <span class="fs-6 text-muted">/isi saldo</span>
                            </div>

                            <ul class="list-unstyled mb-6">
                                @foreach (['Isi saldo, saldo dipotong tiap transaksi', 'Tanpa biaya bulanan tetap', 'Cocok untuk baru mulai / musiman', 'Semua fitur kasir dasar'] as $feature)
                                    <li class="d-flex align-items-center mb-3">
                                        <i class="ki-outline ki-check-circle fs-2 text-success me-3"></i>
                                        <span class="text-gray-700">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <a href="{{ route('deposit.index') }}" class="btn {{ $tenant->isDepositMode() ? 'btn-success' : 'btn-info' }} w-100 mt-auto">
                                <i class="ki-outline ki-wallet fs-3 me-1"></i>
                                {{ $tenant->isDepositMode() ? 'Kelola Deposit' : 'Pilih Starter (Deposit)' }}
                            </a>
                        </div>
                    </div>
                </div>

                @foreach ($plans as $key => $plan)
                    @php
                        $isContact = $plan['contact'] ?? false;
                        $isCurrent = $tenant->plan === $key && $active;
                        $waLink = $isContact
                            ? 'https://wa.me/' . ($plan['wa'] ?? '') . '?text=' . rawurlencode('Halo, saya ingin berlangganan paket ' . $plan['name'] . ' Mooda untuk bisnis "' . $tenant->name . '". Mohon info fitur & harganya.')
                            : null;
                        $periods = $isContact ? [] : \App\Tenancy\Plan::periods($key);
                        $basePpm = $periods[0]['price_per_month'] ?? ($plan['price'] ?? 0);
                        $minPpm  = collect($periods)->min('price_per_month') ?? ($plan['price'] ?? 0);
                        $firstTotal = isset($periods[0]) ? $periods[0]['price_per_month'] * $periods[0]['months'] : ($plan['price'] ?? 0);
                        // Perpanjangan paket AKTIF hanya dibuka H-7 sebelum masa aktif habis.
                        // Bukan paket current (langganan baru/beda paket) => selalu boleh.
                        $until = $tenant->subscription_status === 'trial' ? $tenant->trial_ends_at : $tenant->subscription_ends_at;
                        $canRenew = ! $isCurrent ? true : ($until ? $until->lte(now()->addDays(7)) : true);
                    @endphp
                    <div class="col-md-6 col-lg-5">
                        <div class="card card-flush h-100 border border-2 {{ $isCurrent ? 'border-success' : ($isContact ? 'border-primary' : 'border-gray-200') }}">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h2 class="fw-bolder text-gray-900">{{ $plan['name'] }}</h2>
                                        <div class="text-muted fs-7">{{ $plan['tagline'] }}</div>
                                    </div>
                                    @if ($isCurrent)
                                        <span class="badge badge-success">Paket Anda</span>
                                    @elseif ($isContact)
                                        <span class="badge badge-light-primary">Fleksibel</span>
                                    @endif
                                </div>

                                <div class="my-5">
                                    @if ($isContact)
                                        <span class="fs-3x fw-bolder text-gray-900">Custom</span>
                                        <span class="fs-6 text-muted">/sesuai fitur</span>
                                    @else
                                        {{-- Harga MENGIKUTI durasi yang dipilih (bukan "mulai") supaya
                                             angka besar, pilihan durasi, dan tombol selalu konsisten. --}}
                                        <span class="fs-3x fw-bolder text-gray-900" data-plan-price="{{ $key }}">Rp {{ number_format($basePpm, 0, ',', '.') }}</span>
                                        <span class="fs-6 text-muted">/bulan</span>
                                        @if (count($periods) > 1)
                                            <div class="fs-8 text-muted mt-1">
                                                Termurah Rp {{ number_format($minPpm, 0, ',', '.') }}/bln pada durasi terpanjang
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                <ul class="list-unstyled mb-6">
                                    @foreach ($plan['features'] as $feature)
                                        <li class="d-flex align-items-center mb-3">
                                            <i class="ki-outline ki-check-circle fs-2 text-success me-3"></i>
                                            <span class="text-gray-700">{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if ($isContact)
                                    @if ($isCurrent)
                                        <div class="mt-auto">
                                            <div class="btn btn-light-success w-100 mb-2 disabled">
                                                <i class="ki-outline ki-check-circle fs-3 me-1"></i> Paket Aktif Anda
                                            </div>
                                            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn btn-light-primary w-100">
                                                <i class="ki-outline ki-whatsapp fs-3 me-1"></i> Perpanjang / Konsultasi
                                            </a>
                                        </div>
                                    @else
                                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn btn-primary mt-auto">
                                            <i class="ki-outline ki-whatsapp fs-3 me-1"></i> Konsultasi via WhatsApp
                                        </a>
                                    @endif
                                @else
                                    @if (! config('billing.purchase_enabled', false))
                                        {{-- Pembelian paket dinonaktifkan sementara (Midtrans belum siap) --}}
                                        <button type="button" class="btn btn-light-secondary w-100 mt-auto" disabled>
                                            <i class="ki-outline ki-time fs-3 me-1"></i>
                                            <span>Segera Hadir</span>
                                        </button>
                                    @elseif ($isCurrent && ! $canRenew)
                                        {{-- Paket masih aktif & belum H-7: belum bisa perpanjang, sembunyikan pilihan durasi --}}
                                        <div class="mt-auto">
                                            <button type="button" class="btn btn-light-success w-100" disabled>
                                                <i class="ki-outline ki-check-circle fs-3 me-1"></i>
                                                <span>Plan Saat Ini</span>
                                            </button>
                                            @if ($until)
                                                <div class="text-center fs-8 text-muted mt-2">
                                                    Aktif s/d {{ $until->translatedFormat('d M Y') }} — tombol perpanjang muncul H-7
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        {{-- Pilihan durasi langganan (bisa di-scroll) — hanya saat boleh langganan/perpanjang --}}
                                        <div class="mb-4 mt-auto">
                                            <label class="fw-semibold fs-7 text-muted mb-2 d-block">Pilih durasi langganan</label>
                                            <div class="pe-1" style="max-height: 232px; overflow-y: auto;">
                                                @foreach ($periods as $i => $per)
                                                    @php
                                                        $ppm = (int) $per['price_per_month'];
                                                        $pm = (int) $per['months'];
                                                        $ptotal = $ppm * $pm;
                                                        $disc = $basePpm > 0 ? (int) round((1 - $ppm / $basePpm) * 100) : 0;
                                                    @endphp
                                                    <label class="d-flex align-items-center justify-content-between border border-gray-300 rounded p-3 mb-2 cursor-pointer">
                                                        <span class="d-flex align-items-start">
                                                            <input class="form-check-input mt-1 me-3 plan-period" type="radio"
                                                                name="period-{{ $key }}" value="{{ $pm }}" data-total="{{ $ptotal }}"
                                                                data-ppm="{{ $ppm }}" data-plan="{{ $key }}"
                                                                {{ $i === 0 ? 'checked' : '' }}>
                                                            <span>
                                                                <span class="fw-bold text-gray-900">{{ $per['label'] ?? ($pm . ' Bulan') }}</span>
                                                                @if ($disc > 0)
                                                                    <span class="badge badge-light-success ms-2">Hemat {{ $disc }}%</span>
                                                                @endif
                                                                <span class="d-block fs-8 text-muted">{{ $pm == 1 ? 'Tanpa komitmen' : 'Bayar ' . $pm . ' bulan di muka' }}</span>
                                                            </span>
                                                        </span>
                                                        <span class="text-end text-nowrap ps-2">
                                                            <span class="fw-bolder text-gray-900">Rp {{ number_format($ppm, 0, ',', '.') }}</span><span class="fs-8 text-muted">/bln</span>
                                                            <span class="d-block fs-8 text-muted">Total Rp {{ number_format($ptotal, 0, ',', '.') }}</span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        @php $prefix = ($isCurrent ? 'Perpanjang ' : 'Berlangganan ') . $plan['name']; @endphp
                                        <button type="button"
                                            class="btn {{ $isCurrent ? 'btn-success' : 'btn-light-primary' }} btn-subscribe"
                                            data-plan="{{ $key }}" data-group="period-{{ $key }}" data-prefix="{{ $prefix }}">
                                            @if ($isCurrent)<i class="ki-outline ki-arrows-circle fs-3 me-1"></i>@endif
                                            <span class="btn-subscribe-label">{{ $prefix }} — Rp {{ number_format($firstTotal, 0, ',', '.') }}</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- FITUR TAMBAHAN (ADD-ON) --}}
            @if (($addons ?? collect())->count())
                <div class="card card-flush mb-5">
                    <div class="card-header pt-5">
                        <div>
                            <h3 class="card-title fw-bold text-gray-800 mb-0">Fitur Tambahan</h3>
                            <span class="text-muted fs-8">
                                Modul yang dibeli terpisah, di luar paket langganan.
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Tanggal</th>
                                        <th>Fitur</th>
                                        <th>Rincian</th>
                                        <th>Jumlah</th>
                                        <th>Yang bisa membuka</th>
                                        <th>Status</th>
                                        <th>Berlaku s/d</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($addons as $ad)
                                    @php
                                        $warna = ['active' => 'success', 'pending' => 'warning',
                                                  'expired' => 'secondary', 'cancelled' => 'secondary'][$ad->status] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td>{{ $ad->created_at->translatedFormat('d M Y') }}</td>
                                        <td class="fw-bold text-gray-800">{{ $ad->label }}</td>
                                        <td class="text-muted fs-8">
                                            Rp {{ number_format($ad->price_per_month, 0, ',', '.') }}/bulan
                                            × {{ $ad->months }} bulan
                                            @if ($ad->note)<div>{{ $ad->note }}</div>@endif
                                        </td>
                                        <td class="fw-bold">Rp {{ number_format($ad->amount, 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge badge-light-primary text-capitalize">{{ $ad->labelPeran() }}</span>
                                        </td>
                                        <td><span class="badge badge-light-{{ $warna }} text-uppercase">{{ $ad->status }}</span></td>
                                        <td>{{ $ad->ends_at ? $ad->ends_at->translatedFormat('d M Y') : '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- RIWAYAT PEMBAYARAN --}}
            <div class="card card-flush">
                <div class="card-header pt-5">
                    <h3 class="card-title fw-bold text-gray-800">Riwayat Pembayaran</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Tanggal</th>
                                    <th>Paket</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th>Berlaku s/d</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($history as $sub)
                                    @php
                                        $sc = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'expired' => 'secondary', 'cancelled' => 'secondary'][$sub->status] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td>{{ $sub->created_at->translatedFormat('d M Y H:i') }}</td>
                                        <td>{{ $plans[$sub->plan]['name'] ?? ucfirst($sub->plan) }}</td>
                                        <td>Rp {{ number_format($sub->amount, 0, ',', '.') }}</td>
                                        <td><span class="badge badge-light-{{ $sc }} text-uppercase">{{ $sub->status }}</span></td>
                                        <td>{{ $sub->ends_at ? $sub->ends_at->translatedFormat('d M Y') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-5">Belum ada transaksi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

@endsection

@push('scripts')
    @include('backend.billing._va_modal')
    @if ($driver === 'midtrans')
    <script src="https://app.{{ $isProduction ? '' : 'sandbox.' }}midtrans.com/snap/snap.js"
        data-client-key="{{ $clientKey }}"></script>
    @endif
    <script>
        const BILLING_DRIVER = @json($driver ?? 'midtrans');
        const rp = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
        document.querySelectorAll('.btn-subscribe').forEach(function (btn) {
            const group = btn.dataset.group;
            const prefix = btn.dataset.prefix || 'Berlangganan';
            const labelEl = btn.querySelector('.btn-subscribe-label');
            const selectedRadio = () => group ? document.querySelector('input[name="' + group + '"]:checked') : null;

            function refreshLabel() {
                const r = selectedRadio();
                if (r && labelEl) labelEl.textContent = prefix + ' — ' + rp(r.dataset.total);
                // Angka besar harga/bulan ikut durasi yang dipilih.
                if (r && r.dataset.ppm) {
                    const priceEl = document.querySelector('[data-plan-price="' + r.dataset.plan + '"]');
                    if (priceEl) priceEl.textContent = rp(r.dataset.ppm);
                }
            }
            if (group) {
                document.querySelectorAll('input[name="' + group + '"]').forEach(function (r) {
                    r.addEventListener('change', refreshLabel);
                });
                refreshLabel();
            }

            btn.addEventListener('click', function () {
                const plan = btn.dataset.plan;
                const r = selectedRadio();
                const months = r ? parseInt(r.value, 10) : 1;
                const original = btn.innerHTML;
                const unlock = () => { btn.disabled = false; btn.innerHTML = original; };

                // Tripay: arahkan ke halaman checkout (ringkasan plan+durasi + kartu metode + bayar in-app).
                if (BILLING_DRIVER === 'tripay') {
                    window.location.href = "{{ route('checkout.show') }}?type=subscription&plan=" + encodeURIComponent(plan) + "&months=" + months;
                    return;
                }

                const doCheckout = (opts) => {
                    opts = opts || {};
                    btn.disabled = true;
                    btn.innerHTML = 'Memproses...';
                    fetch("{{ route('billing.checkout') }}", {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ plan: plan, months: months, bank: opts.bank || null, method: opts.method || null }),
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success' && data.driver === 'tripay' && data.checkout_url) {
                            window.location.href = data.checkout_url;
                        } else if (data.status === 'success' && data.driver === 'doku' && data.va_number) {
                            window.showDokuVa(data);
                        } else if (data.status === 'success' && data.snap_token) {
                            if (typeof snap === 'undefined') { alert('Gagal memuat Midtrans.'); return; }
                            snap.pay(data.snap_token, {
                                onSuccess: function () { window.location.reload(); },
                                onPending: function () { window.location.reload(); },
                                onError: function () { alert('Pembayaran gagal. Silakan coba lagi.'); },
                                onClose: function () { /* dibatalkan user */ },
                            });
                        } else {
                            alert(data.message || 'Gagal memproses pembayaran.');
                        }
                    })
                    .catch(() => alert('Terjadi kesalahan jaringan.'))
                    .finally(unlock);
                };

                if (BILLING_DRIVER === 'doku') {
                    window.dokuPickBank().then(bank => doCheckout({ bank: bank })).catch(err => { if (err && err !== '__cancel__') alert(err); });
                } else if (BILLING_DRIVER === 'tripay') {
                    window.tripayPickChannel().then(method => doCheckout({ method: method })).catch(err => { if (err && err !== '__cancel__') alert(err); });
                } else {
                    doCheckout({});
                }
            });
        });
    </script>
@endpush
