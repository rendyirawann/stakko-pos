@extends('backend.layout.app')
@section('title', 'Pengaturan Sistem')
@section('content')

    @php
        $canGeneral = auth()->user()->can('view_data_master');

        // Definisikan di LUAR @if($canGeneral): $previewItemsJson dipakai oleh blok <script>
        // di bawah yang selalu dirender. Saat definisinya masih di dalam tab General, pengguna
        // tanpa izin view_data_master membuka halaman ini dan langsung kena 500.
        // Label pajak mengikuti VERTICAL: PB1 hanya untuk restoran/F&B;
        // laundry & retail memakai istilah pajak umum (PPN).
        $stIsLaundry = ($currentTenant ?? null) && $currentTenant->isLaundry();
        $taxLabel = $stIsLaundry ? 'Pajak / PPN' : 'Pajak Restoran (PB1)';

        // Item contoh untuk pratinjau struk (dipakai di blok <script> bawah).
        $previewItemsJson = json_encode($stIsLaundry
            ? [
                ['name' => 'Cuci Setrika Kiloan', 'qty' => 3, 'price' => 9000, 'subtotal' => 27000, 'notes' => 'parfum lavender'],
                ['name' => 'Bed Cover', 'qty' => 1, 'price' => 25000, 'subtotal' => 25000],
            ]
            : [
                ['name' => 'Kopi Susu', 'qty' => 2, 'price' => 18000, 'subtotal' => 36000, 'addons' => [['name' => 'Extra Shot']], 'notes' => 'less ice'],
                ['name' => 'Roti Bakar', 'qty' => 1, 'price' => 15000, 'subtotal' => 15000],
            ]);
    @endphp

    <div id="kt_app_content" class="app-content flex-column-fluid mt-5">
        <div id="kt_app_content_container" class="app-container container-xxl">

            <form action="{{ route('settings.update') }}" method="POST" id="form-settings">
                @csrf
                <div class="card shadow-sm">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title fw-bold m-0"><i class="ki-outline ki-setting-2 fs-2 me-2"></i> Konfigurasi Sistem</h3>
                        <ul class="nav nav-tabs nav-line-tabs ms-auto border-0" role="tablist">
                            @if ($canGeneral)
                                <li class="nav-item"><a class="nav-link active fw-semibold" data-bs-toggle="tab" href="#tab-umum">Umum</a></li>
                                <li class="nav-item"><a class="nav-link fw-semibold" data-bs-toggle="tab" href="#tab-struk">Struk</a></li>
                            @endif
                            <li class="nav-item"><a class="nav-link {{ $canGeneral ? '' : 'active' }} fw-semibold" data-bs-toggle="tab" href="#tab-printer">Printer</a></li>
                        </ul>
                    </div>

                    <div class="card-body tab-content">
                        {{-- ========== TAB UMUM (owner/admin/Superadmin) ========== --}}
                        @if ($canGeneral)
                        <div class="tab-pane fade show active" id="tab-umum">
                            <div class="row mb-6">
                                <label class="col-lg-3 col-form-label required fw-semibold fs-6">Nama Toko</label>
                                <div class="col-lg-9">
                                    <input type="text" name="store_name" class="form-control form-control-solid"
                                        value="{{ old('store_name', $setting->store_name) }}" required>
                                </div>
                            </div>
                            <div class="row mb-6">
                                <label class="col-lg-3 col-form-label fw-semibold fs-6">Alamat Toko</label>
                                <div class="col-lg-9">
                                    <textarea name="address" class="form-control form-control-solid" rows="3">{{ old('address', $setting->address) }}</textarea>
                                </div>
                            </div>
                            <div class="row mb-6">
                                <label class="col-lg-3 col-form-label fw-semibold fs-6">No. Telepon / WA</label>
                                <div class="col-lg-9">
                                    <input type="text" name="phone" class="form-control form-control-solid"
                                        value="{{ old('phone', $setting->phone) }}">
                                </div>
                            </div>
                            <div class="row mb-2">
                                <label class="col-lg-3 col-form-label required fw-semibold fs-6">{{ $taxLabel }}</label>
                                <div class="col-lg-9">
                                    <div class="input-group input-group-solid w-200px">
                                        <input type="number" name="tax_rate" class="form-control form-control-solid js-no-format"
                                            value="{{ old('tax_rate', $setting->tax_rate) }}" min="0" max="100" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text">Masukkan angka 0 jika toko tidak membebankan pajak ke pelanggan.</div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- ========== TAB STRUK (owner/admin/Superadmin) ========== --}}
                        @if ($canGeneral)
                        <div class="tab-pane fade" id="tab-struk">
                            <div class="row">
                                <div class="col-lg-7">
                                    <div class="alert alert-primary d-flex align-items-center">
                                        <i class="ki-outline ki-information-5 fs-2x text-primary me-3"></i>
                                        <span class="fs-7 text-gray-700">Sesuaikan tampilan struk. Nama toko, alamat &amp; telepon diambil dari tab <b>Umum</b>.</span>
                                    </div>

                                    <div class="mb-6">
                                        <label class="fw-semibold fs-6 mb-2">Teks Header (opsional)</label>
                                        <input type="text" name="receipt_header" class="form-control form-control-solid"
                                            maxlength="120" value="{{ old('receipt_header', $setting->receipt_header) }}"
                                            placeholder="{{ $stIsLaundry ? 'mis. Cabang Bandung / Laundry Express' : 'mis. Cabang Bandung / Coffee & Eatery' }}">
                                        <div class="form-text">Muncul tepat di bawah nama toko.</div>
                                    </div>

                                    <div class="mb-6">
                                        <label class="fw-semibold fs-6 mb-2">Teks Footer / Ucapan (opsional)</label>
                                        <textarea name="receipt_footer" class="form-control form-control-solid" rows="3"
                                            maxlength="255" placeholder="mis. Terima kasih atas kunjungan Anda!">{{ old('receipt_footer', $setting->receipt_footer) }}</textarea>
                                        <div class="form-text">Muncul di kaki struk. Boleh beberapa baris (mis. IG: @@tokoanda). Kosongkan bila tidak ingin ada footer.</div>
                                    </div>

                                    <label class="fw-semibold fs-6 mb-3 d-block">Tampilkan di Struk</label>
                                    <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                                        <input class="form-check-input" type="checkbox" name="receipt_show_address" value="1"
                                            {{ ($errors->any() ? old('receipt_show_address') : ($setting->receipt_show_address ?? true)) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold ms-3">Alamat toko</span>
                                    </label>
                                    <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                                        <input class="form-check-input" type="checkbox" name="receipt_show_phone" value="1"
                                            {{ ($errors->any() ? old('receipt_show_phone') : ($setting->receipt_show_phone ?? true)) ? 'checked' : '' }}>
                                        <span class="form-check-label fw-semibold ms-3">No. Telepon</span>
                                    </label>
                                </div>

                                {{-- Pratinjau struk (live) --}}
                                <div class="col-lg-5 mt-6 mt-lg-0">
                                    <label class="fw-semibold fs-6 mb-2 d-block">Pratinjau</label>
                                    <div class="bg-light rounded border p-4 d-flex justify-content-center">
                                        <pre id="struk-preview" style="font-family:'Courier New',monospace; font-size:12px; line-height:1.3; white-space:pre; margin:0; background:#fff; padding:10px; border:1px dashed #bbb; border-radius:4px; overflow-x:auto; max-width:100%;"></pre>
                                    </div>
                                    <div class="form-text">Contoh data; mengikuti ukuran kertas di tab Printer.</div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- ========== TAB PRINTER (semua role) ========== --}}
                        <div class="tab-pane fade {{ $canGeneral ? '' : 'show active' }}" id="tab-printer">
                            <div class="alert alert-primary d-flex align-items-center">
                                <i class="ki-outline ki-information-5 fs-2x text-primary me-3"></i>
                                <span class="fs-7 text-gray-700">Pilih cara sistem menyambung ke printer thermal saat mencetak struk.
                                    Sesuaikan dengan perangkat Anda (PC/laptop atau tablet) & jenis koneksi printer (USB / LAN / Bluetooth).</span>
                            </div>

                            {{-- Ukuran kertas --}}
                            <div class="row mb-6 mt-4">
                                <label class="col-lg-3 col-form-label fw-semibold fs-6">Ukuran Kertas</label>
                                <div class="col-lg-9">
                                    <select name="paper_width" class="form-select form-select-solid w-250px">
                                        <option value="58" {{ (int) ($setting->paper_width ?? 58) === 58 ? 'selected' : '' }}>58 mm (32 kolom)</option>
                                        <option value="80" {{ (int) ($setting->paper_width ?? 58) === 80 ? 'selected' : '' }}>80 mm (48 kolom)</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Metode cetak --}}
                            <label class="fw-semibold fs-6 mb-3 d-block">Metode Cetak</label>
                            @php
                                $cur = $setting->printer_method ?? 'auto';
                                $methods = [
                                    ['auto', 'Otomatis (Rekomendasi)', 'Sistem memilih sendiri: di aplikasi tablet → printer bawaan aplikasi; selain itu → dialog browser.', 'ki-technology-4', null, null],
                                    ['browser', 'Dialog Browser / OS', 'Cetak lewat dialog print sistem. Printer thermal harus terpasang sebagai printer OS (driver). Cocok PC/laptop + USB/LAN.', 'ki-printer', null, null],
                                    ['qztray', 'QZ Tray (Desktop)', 'Cetak ESC/POS langsung tanpa dialog di PC/laptop (USB/LAN/Bluetooth). Perlu aplikasi QZ Tray berjalan di komputer.', 'ki-desktop', 'Download QZ Tray', 'https://qz.io/download/'],
                                    ['webbluetooth', 'Web Bluetooth (BLE)', 'Sambung printer thermal Bluetooth langsung dari browser Chrome/Edge. Cocok tablet/laptop. Perlu akses HTTPS.', 'ki-technology-2', 'Butuh Chrome / Edge', 'https://www.google.com/chrome/'],
                                    ['rawbt', 'RawBT (Android)', 'Cetak dari browser Android via aplikasi RawBT (Bluetooth / USB / WiFi). Cocok tablet dengan berbagai printer.', 'ki-tablet', 'Download RawBT', 'https://play.google.com/store/apps/details?id=ru.a402d.rawbtprinter'],
                                ];
                            @endphp
                            <div class="row g-4">
                                @foreach ($methods as [$val, $title, $desc, $icon, $dlText, $dlUrl])
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="printer_method" value="{{ $val }}"
                                            id="pm_{{ $val }}" {{ $cur === $val ? 'checked' : '' }}>
                                        <label for="pm_{{ $val }}"
                                            class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-start text-start p-4 h-100">
                                            <i class="ki-outline {{ $icon }} fs-2x text-primary me-3 mt-1"></i>
                                            <span>
                                                <span class="d-block fw-bold fs-5 text-gray-900">{{ $title }}</span>
                                                <span class="d-block text-muted fs-7">{{ $desc }}</span>
                                            </span>
                                        </label>
                                        @if ($dlUrl)
                                            <div class="mt-2 ps-1">
                                                <a href="{{ $dlUrl }}" target="_blank" rel="noopener"
                                                    class="btn btn-sm btn-light-primary">
                                                    <i class="ki-outline ki-cloud-download fs-5"></i> {{ $dlText }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="separator my-6"></div>
                            <label class="fw-semibold fs-6 mb-2 d-block">Uji Printer</label>
                            <div class="d-flex flex-wrap gap-3">
                                {{-- Tombol "Hubungkan" hanya untuk metode yang perlu memilih perangkat (BLE/QZ/APK) --}}
                                <button type="button" class="btn btn-light-primary d-none" id="btn-connect-printer">
                                    <i class="ki-outline ki-plug fs-3"></i> <span id="connect-label">Hubungkan Printer</span>
                                </button>
                                <button type="button" class="btn btn-light-success" id="btn-test-print">
                                    <i class="ki-outline ki-printer fs-3"></i> Test Cetak
                                </button>
                            </div>
                            {{-- Petunjuk otomatis sesuai metode terpilih --}}
                            <div class="form-text mt-3" id="printer-hint"></div>

                            {{-- Izin Bluetooth permanen: hanya tampil bila metode Web Bluetooth dipilih.
                                 Tanpa ini printer harus dihubungkan ulang tiap pindah halaman. --}}
                            <div class="separator my-6 d-none" id="ble-persist-sep"></div>
                            <div class="d-none bg-light-primary rounded border border-primary border-dashed p-5" id="ble-persist-panel">
                                <div class="d-flex">
                                    <i class="ki-outline ki-shield-tick fs-2x text-primary me-4 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <h4 class="fw-bold text-gray-900 mb-0">Agar printer diingat antar halaman</h4>
                                            <span class="badge badge-light" id="ble-persist-status">memeriksa…</span>
                                        </div>
                                        <div class="fs-7 text-gray-700" id="ble-persist-body"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Cetak senyap / kiosk untuk metode Dialog Browser --}}
                            <div class="separator my-6"></div>
                            <div class="d-flex bg-light-warning rounded border border-warning border-dashed p-5">
                                <i class="ki-outline ki-rocket fs-2x text-warning me-4 mt-1"></i>
                                <div>
                                    <h4 class="fw-bold text-gray-900 mb-2">Cetak otomatis tanpa dialog (mode Kiosk)</h4>
                                    <div class="fs-7 text-gray-700">
                                        Khusus metode <b>Dialog Browser / OS</b>: agar dialog print tidak perlu diklik
                                        (langsung tercetak ke printer default), jalankan browser dengan flag
                                        <code>--kiosk-printing</code> lewat <b>shortcut aplikasi</b>:
                                        <div class="bg-dark text-white rounded p-3 my-2" style="font-family:monospace; overflow-x:auto;">
                                            chrome.exe --kiosk-printing --app={{ url('/admin/kasir') }}
                                        </div>
                                        Edge: <code>msedge.exe --kiosk-printing --app=&lt;URL&gt;</code>.
                                        Tambahkan <code>--kiosk</code> untuk layar penuh.
                                        <br>Lalu di Windows: <b>Settings → Printers</b> → jadikan printer thermal sebagai
                                        <b>default</b>, dan set ukuran kertas driver ke 58/80mm.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end py-6 px-9">
                        <button type="submit" class="btn btn-primary" id="btn-save">Simpan Pengaturan</button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                @if (session('success'))
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: '{{ session('success') }}', timer: 3000 });
                @endif
                @if ($errors->any())
                    Swal.fire({ icon: 'error', title: 'Gagal menyimpan', html: `{!! implode('<br>', array_map('e', $errors->all())) !!}` });
                @endif

                // Petunjuk + tombol "Hubungkan" menyesuaikan metode yang sedang dipilih
                const HINTS = {
                    webbluetooth: 'Klik "Hubungkan Printer Bluetooth", pilih printer BLE Anda, lalu "Test Cetak". Wajib Chrome/Edge (bukan Brave) + HTTPS. Agar printer tidak perlu dihubungkan ulang tiap pindah halaman, ikuti kotak biru di bawah.',
                    qztray: 'Pastikan aplikasi QZ Tray berjalan di komputer ini, klik "Pilih Printer" untuk memilih printer, lalu "Test Cetak".',
                    native: 'Klik "Pilih Printer" untuk memilih printer Bluetooth yang sudah dipasangkan di tablet, lalu "Test Cetak".',
                    browser: 'Tidak perlu "Hubungkan". Jadikan printer thermal sebagai printer default OS, lalu klik "Test Cetak" (akan lewat dialog print / senyap jika mode kiosk).',
                    rawbt: 'Tidak perlu "Hubungkan". Pastikan aplikasi RawBT terpasang di Android, lalu klik "Test Cetak" (akan diteruskan ke RawBT).',
                    auto: 'Mode otomatis: di aplikasi tablet (APK) memakai printer bawaan; di browser memakai dialog print OS. Klik "Test Cetak" untuk menguji.',
                };
                function refreshPrinterControls() {
                    if (!window.MoodaPrint) return;
                    const m = window.MoodaPrint.resolveMethod();
                    const needs = window.MoodaPrint.needsButton();
                    $('#btn-connect-printer').toggleClass('d-none', !needs);
                    if (needs) $('#connect-label').text(window.MoodaPrint.buttonLabel());
                    $('#printer-hint').text(HINTS[m] || HINTS[$('input[name=printer_method]:checked').val()] || '');

                    const isBle = (m === 'webbluetooth');
                    $('#ble-persist-panel, #ble-persist-sep').toggleClass('d-none', !isBle);
                    if (isBle) renderBlePersist();
                }

                // ===== Izin Bluetooth permanen =====
                // Halaman web TIDAK BOLEH menavigasi ke chrome:// / edge:// (diblokir browser demi
                // keamanan -- kalau tidak, sembarang situs bisa menyalakan fitur eksperimental di
                // browser pengunjung). Jadi yang bisa kita berikan adalah tautan tepat ke flag-nya
                // plus tombol salin, tinggal ditempel di address bar.
                const FLAG_ID = 'enable-web-bluetooth-new-permissions-backend';

                function detectBrowser() {
                    const ua = navigator.userAgent || '';
                    if (navigator.brave)             return { scheme: null,       name: 'Brave',             ok: false };
                    if (/Edg\//.test(ua))            return { scheme: 'edge://',  name: 'Edge',              ok: true  };
                    if (/OPR\//.test(ua))            return { scheme: 'opera://', name: 'Opera',             ok: true  };
                    if (/SamsungBrowser\//.test(ua)) return { scheme: null,       name: 'Samsung Internet',  ok: false };
                    if (/Firefox\//.test(ua))        return { scheme: null,       name: 'Firefox',           ok: false };
                    if (/Chrome\//.test(ua))         return { scheme: 'chrome://',name: 'Chrome',            ok: true  };
                    if (/Safari\//.test(ua))         return { scheme: null,       name: 'Safari',            ok: false };
                    return { scheme: null, name: 'Browser ini', ok: false };
                }

                function renderBlePersist() {
                    const b = detectBrowser();
                    const $st = $('#ble-persist-status'), $body = $('#ble-persist-body');

                    if (navigator.bluetooth && navigator.bluetooth.getDevices) {
                        $st.attr('class', 'badge badge-light-success').text('Sudah aktif');
                        $body.html('Printer tersambung sendiri tiap membuka halaman Kasir — tidak perlu klik ' +
                                   '<b>Hubungkan</b> lagi. Tidak ada yang perlu diubah.');
                        return;
                    }
                    if (!navigator.bluetooth || !b.ok) {
                        $st.attr('class', 'badge badge-light-danger').text('Tidak didukung');
                        $body.html('<b>' + b.name + '</b> tidak mendukung Web Bluetooth. Gunakan <b>Google Chrome</b> ' +
                                   'di perangkat kasir, lalu buka halaman ini lagi.');
                        return;
                    }
                    const url = b.scheme + 'flags/#' + FLAG_ID;
                    $st.attr('class', 'badge badge-light-warning').text('Belum aktif');
                    $body.html(
                        'Saat ini printer harus dihubungkan ulang setiap pindah halaman. <b>' + b.name + '</b> masih ' +
                        'menaruh kemampuan "mengingat perangkat" di balik sebuah flag — nyalakan sekali saja di perangkat kasir:' +
                        '<div class="bg-dark text-white rounded p-3 my-3" style="font-family:monospace; overflow-x:auto;">' + url + '</div>' +
                        '<button type="button" class="btn btn-sm btn-primary mb-3" id="btn-copy-flag">' +
                        '<i class="ki-outline ki-copy fs-4"></i> Salin Tautan</button>' +
                        '<ol class="mb-0 ps-4">' +
                        '<li>Salin tautan di atas, tempel di address bar <b>' + b.name + '</b></li>' +
                        '<li>Ubah pilihannya menjadi <b>Enabled</b></li>' +
                        '<li>Restart ' + b.name + ', lalu hubungkan printer sekali lagi</li>' +
                        '</ol>' +
                        '<div class="text-muted fs-8 mt-3">Tautan ini tidak bisa dibuka lewat tombol: browser melarang ' +
                        'halaman web membuka alamat <b>' + b.scheme + '</b> demi keamanan, jadi harus ditempel manual.</div>'
                    );
                }

                $(document).on('click', '#btn-copy-flag', function () {
                    const b = detectBrowser();
                    if (!b.scheme) return;
                    const url = b.scheme + 'flags/#' + FLAG_ID;
                    const done = () => Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                        title: 'Tautan disalin — tempel di address bar', showConfirmButton: false, timer: 3000 });
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(url).then(done).catch(() => fallbackCopy(url, done));
                    } else { fallbackCopy(url, done); }
                });

                function fallbackCopy(text, done) {
                    try {
                        const ta = document.createElement('textarea');
                        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                        document.body.appendChild(ta); ta.select();
                        document.execCommand('copy'); document.body.removeChild(ta); done();
                    } catch (e) {
                        Swal.fire('Salin manual', 'Tidak bisa menyalin otomatis. Ketik manual: ' + text, 'info');
                    }
                }

                $('input[name="printer_method"]').on('change', function() {
                    if (window.MOODA_PRINT) window.MOODA_PRINT.method = this.value;
                    refreshPrinterControls();
                });
                $('select[name="paper_width"]').on('change', function() {
                    if (window.MOODA_PRINT) window.MOODA_PRINT.paper_width = parseInt(this.value, 10);
                });

                $('#btn-test-print').on('click', function() {
                    if (window.MoodaPrint) window.MoodaPrint.test();
                    else Swal.fire('Info', 'Engine cetak belum termuat, muat ulang halaman.', 'info');
                });
                $('#btn-connect-printer').on('click', function() {
                    if (window.MoodaPrint) window.MoodaPrint.quickConnect();
                });

                refreshPrinterControls();

                // ===== Pratinjau struk (tab Struk) — memakai engine cetak yang sama =====
                // Contoh item pratinjau struk sesuai vertical tenant.
                const PREVIEW_ITEMS = {!! $previewItemsJson !!};

                function buildPreviewReceipt() {
                    const showAddr = $('input[name="receipt_show_address"]').is(':checked');
                    const showPhone = $('input[name="receipt_show_phone"]').is(':checked');
                    // Subtotal dihitung dari PREVIEW_ITEMS supaya selalu cocok dgn item contoh.
                    const subtotal = PREVIEW_ITEMS.reduce((s, it) => s + (it.subtotal || 0), 0);
                    const discount = 0;
                    const net = Math.max(0, subtotal - discount);
                    const rate = parseFloat($('input[name="tax_rate"]').val()) || 0;   // ikut nilai Pajak di tab Umum
                    const tax = Math.round(net * (rate / 100));
                    const grand = net + tax;
                    const cash = 60000;
                    return {
                        store_name: ($('input[name="store_name"]').val() || 'Mooda'),
                        store_address: showAddr ? ($('textarea[name="address"]').val() || '') : '',
                        store_phone: showPhone ? ($('input[name="phone"]').val() || '') : '',
                        receipt_header: $('input[name="receipt_header"]').val() || '',
                        receipt_footer: $('textarea[name="receipt_footer"]').val() || '',
                        invoice_no: 'MDA-INV-CONTOH', queue_number: 7, customer_name: 'Budi',
                        datetime: '01/01/2026 12.00',
                        // Contoh item mengikuti VERTICAL (laundry: layanan cuci; F&B: menu).
                        items: PREVIEW_ITEMS,
                        subtotal: subtotal, discount_amount: discount, tax: tax, tax_rate: rate, grand_total: grand,
                        payment_method: 'cash', payment_status: 'paid', cash_received: cash, change_amount: Math.max(0, cash - grand),
                    };
                }
                function renderStrukPreview() {
                    const el = document.getElementById('struk-preview');
                    if (!el || !window.MoodaPrint || !window.MoodaPrint.preview) return;
                    const w = parseInt($('select[name="paper_width"]').val(), 10) || 58;
                    if (window.MOODA_PRINT) window.MOODA_PRINT.paper_width = w;
                    el.textContent = window.MoodaPrint.preview(buildPreviewReceipt());
                }
                $('input[name="receipt_header"], textarea[name="receipt_footer"], input[name="store_name"], textarea[name="address"], input[name="phone"], input[name="tax_rate"], input[name="receipt_show_address"], input[name="receipt_show_phone"], select[name="paper_width"]')
                    .on('input change', renderStrukPreview);
                renderStrukPreview();

                $('#form-settings').on('submit', function() {
                    let btn = $('#btn-save');
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2 align-middle"></span> Memproses...');
                    Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                });
            });
        </script>
    @endpush
@endsection
