@extends('backend.layout.app')
@section('title', 'Manajemen Shift Kasir')
@section('content')

    @php
        $isUmkm = optional($currentTenant)->isUmkm();
        $L = $isUmkm ? 'Kas' : 'Shift';
    @endphp

    <div id="kt_app_content" class="app-content flex-column-fluid mt-5">
        <div id="kt_app_content_container" class="app-container container-xxl">

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-center p-5 mb-10">
                    <i class="ki-outline ki-shield-tick fs-2hx text-success me-4"></i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-success">Berhasil</h4><span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                    <i class="ki-outline ki-information-5 fs-2hx text-danger me-4"></i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-danger">Gagal</h4><span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <div class="row g-5 g-xl-10">
                <div class="col-xl-5">
                    @if ($canOperate)
                        {{-- ================= OPERATOR (KASIR) ================= --}}
                        @if (!$currentShift)
                            <div class="card shadow-sm border-0">
                                <div class="card-body text-center p-10">
                                    @if ($blockingShift ?? null)
                                        {{-- Aturan 1 shift aktif per toko: shift orang lain sedang berjalan --}}
                                        <i class="ki-outline ki-lock-2 fs-5x text-warning mb-5"></i>
                                        <h2 class="fs-2x fw-bold text-gray-800 mb-2">{{ $L }} Sedang Berjalan</h2>
                                        <p class="text-gray-500 fs-5 mb-6">
                                            {{ $L }} sedang dibuka atas nama
                                            <b>{{ optional($blockingShift->user)->name ?? 'pengguna lain' }}</b>
                                            (sejak {{ \Carbon\Carbon::parse($blockingShift->start_time)->translatedFormat('d M Y, H:i') }}).
                                        </p>
                                        <div class="alert alert-warning fs-6 mb-0">
                                            Hanya <b>1 {{ strtolower($L) }} aktif per toko</b> — Anda baru bisa membuka
                                            {{ strtolower($L) }} setelah {{ strtolower($L) }} tersebut ditutup.
                                        </div>
                                    @else
                                    <i class="ki-outline ki-time fs-5x text-primary mb-5"></i>
                                    <h2 class="fs-2x fw-bold text-gray-800 mb-2">{{ $L }} Belum Dibuka</h2>
                                    <p class="text-gray-500 fs-5 mb-8">Anda harus membuka {{ strtolower($L) }} dan memasukkan modal
                                        sebelum dapat menggunakan mesin kasir.</p>

                                    <form action="{{ route('shifts.open') }}" method="POST" id="formOpenShift">
                                        @csrf

                                        @if ($needTarget)
                                            <div class="bg-light-primary rounded p-5 mb-6 text-start">
                                                <div class="d-flex align-items-center mb-3">
                                                    <i class="ki-outline ki-sun fs-1 text-primary me-2"></i>
                                                    <span class="fw-bold text-primary fs-5">Setup Harian</span>
                                                </div>

                                                @if ($needTarget)
                                                    <div class="mb-4">
                                                        <label class="required fw-semibold fs-6 mb-1">{{ (($currentTenant ?? null) && $currentTenant->isLaundry()) ? 'Target Profit Hari Ini (Rp)' : 'Target Penjualan Hari Ini (Rp)' }}</label>
                                                        <input type="number" name="target_penjualan"
                                                            class="form-control form-control-solid" placeholder="Contoh: 3000000"
                                                            min="0" required>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="text-start mb-6">
                                            <label class="required fw-semibold fs-5 mb-2">Uang Modal Laci (kembalian + pengeluaran) (Rp)</label>
                                            <input type="number" name="starting_cash"
                                                class="form-control form-control-lg form-control-solid text-center fs-3 fw-bold"
                                                placeholder="Contoh: 500000" min="0" required autofocus>
                                            <div class="form-text fs-8">Uang tunai yang ditaruh di laci: untuk kembalian sekaligus
                                                kas belanja/pengeluaran hari ini (jadi satu).</div>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-lg w-100 fs-4 fw-bold">
                                            <i class="ki-outline ki-unlock fs-2 me-2"></i> Buka {{ $L }} Sekarang
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="card shadow-sm border-0">
                                <div class="card-header bg-light-primary pt-7 border-0">
                                    <h3 class="card-title align-items-start flex-column">
                                        <span class="card-label fw-bold text-primary fs-3"><i
                                                class="ki-outline ki-security-user fs-2 text-primary me-2"></i> {{ $L }} Sedang
                                            Berjalan</span>
                                        <span class="text-primary mt-1 fw-semibold fs-7">Dimulai:
                                            {{ \Carbon\Carbon::parse($currentShift->start_time)->translatedFormat('d M Y, H:i') }}</span>
                                    </h3>
                                </div>
                                <div class="card-body p-8">
                                    <div class="d-flex flex-stack mb-5">
                                        <span class="text-gray-600 fs-5">Uang Modal Laci <span class="text-muted fs-8">(kembalian + pengeluaran)</span></span>
                                        <span class="d-flex align-items-center gap-2">
                                            <span class="text-gray-800 fw-bold fs-4">Rp
                                                {{ number_format($currentShift->starting_cash, 0, ',', '.') }}</span>
                                            @if ($canReopen)
                                                {{-- Salah ketik modal saat buka shift dibetulkan di sini, tanpa perlu
                                                     menutup lalu membuka ulang shiftnya. --}}
                                                <a href="#" class="js-edit-modal btn btn-sm btn-icon btn-light-primary"
                                                    title="Koreksi modal laci"
                                                    data-id="{{ $currentShift->id }}"
                                                    data-modal="{{ (int) $currentShift->starting_cash }}"
                                                    data-name="{{ e(optional($currentShift->user)->name) }}">
                                                    <i class="ki-outline ki-pencil fs-5"></i>
                                                </a>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="d-flex flex-stack mb-5">
                                        <span class="text-gray-600 fs-5">Pendapatan Tunai (Masuk)</span>
                                        <span class="text-success fw-bold fs-4">+ Rp
                                            {{ number_format($cashSales, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex flex-stack mb-5">
                                        <span class="text-gray-600 fs-5">Pendapatan QRIS <span class="text-muted fs-8">(info, tak masuk laci)</span></span>
                                        <span class="text-info fw-bold fs-4">Rp
                                            {{ number_format($qrisSales ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex flex-stack mb-5">
                                        <span class="text-gray-600 fs-5">Pengeluaran (Keluar)</span>
                                        <span class="text-danger fw-bold fs-4">- Rp
                                            {{ number_format($shiftExpenses ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="separator separator-dashed my-5"></div>
                                    <div class="d-flex flex-stack mb-8">
                                        <span class="text-gray-800 fw-bolder fs-4">Uang Fisik Seharusnya</span>
                                        <span class="text-primary fw-bolder fs-2qx">Rp
                                            {{ number_format($currentShift->starting_cash + $cashSales - ($shiftExpenses ?? 0), 0, ',', '.') }}</span>
                                    </div>

                                    <form action="{{ route('shifts.close', $currentShift->id) }}" method="POST"
                                        id="formCloseShift">
                                        @csrf
                                        <div class="bg-light-warning rounded p-6 mb-6">
                                            <label class="required fw-bold fs-5 text-gray-800 mb-2">Uang Fisik Aktual (Rp)</label>
                                            <p class="text-muted fs-7 mb-4">Hitung SEMUA uang tunai di laci sekarang (modal + hasil tunai −
                                                pengeluaran), lalu masukkan totalnya untuk menutup {{ strtolower($L) }}.</p>
                                            <input type="number" name="actual_cash"
                                                class="form-control form-control-lg text-center fs-2x fw-bold" placeholder="0"
                                                min="0" required>
                                        </div>
                                        <button type="button" onclick="confirmClose()"
                                            class="btn btn-danger btn-lg w-100 fs-4 fw-bold">
                                            <i class="ki-outline ki-lock-3 fs-2 me-2"></i> Tutup {{ $L }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if (! $ownOnly)
                        {{-- ================= PANTAU SHIFT TOKO (OWNER/ADMIN/SUPERADMIN) ================= --}}
                        <div class="card shadow-sm border-0 {{ $canOperate ? 'mt-6' : '' }}">
                            <div class="card-header bg-light-info pt-7 border-0">
                                <h3 class="card-title fw-bold text-info fs-3">
                                    <i class="ki-outline ki-eye fs-2 text-info me-2"></i> Pantau {{ $L }} Toko
                                </h3>
                            </div>
                            <div class="card-body p-8">
                                <p class="text-gray-600 fs-6 mb-6">Semua {{ strtolower($L) }} yang sedang berjalan di toko tampil di sini.
                                    Anda juga dapat <b>membuka kembali</b> {{ strtolower($L) }} yang tak sengaja ditutup lewat daftar
                                    riwayat di samping.</p>
                                <h4 class="fw-bold text-gray-800 fs-5 mb-4">{{ $L }} Sedang Berjalan</h4>
                                @forelse($openShiftsAll as $os)
                                    <div class="d-flex flex-stack border border-dashed rounded p-4 mb-3">
                                        <div>
                                            <span class="d-block fw-bold text-gray-800">{{ optional($os->user)->name ?? 'Kasir' }}</span>
                                            <span class="d-block text-muted fs-8">Sejak
                                                {{ \Carbon\Carbon::parse($os->start_time)->translatedFormat('d M Y, H:i') }}</span>
                                        </div>
                                        <div class="text-end align-self-center">
                                            <div class="badge badge-light-success mb-1">Modal Rp
                                                {{ number_format($os->starting_cash, 0, ',', '.') }}</div>
                                            @if ($canReopen)
                                                <div>
                                                    <a href="#" class="js-edit-modal fs-8 fw-bold text-primary"
                                                        data-id="{{ $os->id }}" data-modal="{{ (int) $os->starting_cash }}"
                                                        data-name="{{ e(optional($os->user)->name) }}"><i class="ki-outline ki-pencil fs-8"></i> Edit modal</a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted text-center py-6">Tidak ada {{ strtolower($L) }} {{ $canOperate ? 'lain ' : '' }}yang sedang berjalan.</div>
                                @endforelse

                                <div class="separator separator-dashed my-6"></div>
<a href="{{ route('kasir.index') }}" class="btn btn-primary btn-lg w-100 fs-4 fw-bold">
    <i class="ki-outline ki-handcart fs-2 me-2"></i> Lihat Layar Kasir
</a>

                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-xl-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header pt-7 border-0">
                            <h3 class="card-title fw-bold text-gray-800 fs-3">
                                Riwayat {{ $L }} {{ $ownOnly ? 'Anda' : 'Toko' }}
                            </h3>
                        </div>
                        <div class="card-body pt-3">
                            <div class="table-responsive">
                                <table class="table align-middle table-row-dashed fs-6 gy-4">
                                    <thead>
                                        <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                            <th>Waktu Buka - Tutup</th>
                                            @if (!$ownOnly)
                                                <th>Kasir</th>
                                            @endif
                                            <th class="text-end">Modal</th>
                                            <th class="text-end">Aktual</th>
                                            <th class="text-end">Selisih</th>
                                            @if ($canReopen)
                                                <th class="text-end">Aksi</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($history as $hist)
                                            <tr>
                                                <td>
                                                    <span class="d-block fw-bold text-gray-800">{{ \Carbon\Carbon::parse($hist->start_time)->format('d/m/Y H:i') }}</span>
                                                    <span class="d-block text-muted fs-8">s/d
                                                        {{ \Carbon\Carbon::parse($hist->end_time)->format('H:i') }}</span>
                                                </td>
                                                @if (!$ownOnly)
                                                    <td><span class="fw-semibold text-gray-700">{{ optional($hist->user)->name ?? '-' }}</span></td>
                                                @endif
                                                <td class="text-end">Rp
                                                    {{ number_format($hist->starting_cash, 0, ',', '.') }}</td>
                                                <td class="text-end fw-semibold">Rp
                                                    {{ number_format($hist->actual_cash, 0, ',', '.') }}</td>
                                                <td class="text-end">
                                                    @if ($hist->difference == 0)
                                                        <span class="badge badge-light-success">Pas (Rp 0)</span>
                                                    @elseif($hist->difference > 0)
                                                        <span class="badge badge-light-info">Lebih +Rp
                                                            {{ number_format($hist->difference, 0, ',', '.') }}</span>
                                                    @else
                                                        <span class="badge badge-light-danger">Kurang Rp
                                                            {{ number_format(abs($hist->difference), 0, ',', '.') }}</span>
                                                    @endif
                                                </td>
                                                @if ($canReopen)
                                                    <td class="text-end">
                                                        @if ($hist->end_time && \Carbon\Carbon::parse($hist->end_time)->isToday())
                                                            {{-- Membuka kembali menghapus angka penutup, jadi alasannya
                                                                 diminta lewat modal (bukan confirm biasa) supaya ikut tercatat. --}}
                                                            <button type="button" class="btn btn-sm btn-light-warning fw-bold js-buka-kembali"
                                                                data-url="{{ route('shifts.reopen', $hist->id) }}"
                                                                data-kasir="{{ optional($hist->user)->name }}"
                                                                data-jam="{{ \Carbon\Carbon::parse($hist->end_time)->format('H:i') }}"
                                                                data-aktual="{{ number_format((float) $hist->actual_cash, 0, ',', '.') }}">
                                                                <i class="ki-outline ki-arrow-circle-left fs-5"></i> Buka Kembali
                                                            </button>
                                                        @else
                                                            <span class="text-muted fs-8">—</span>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">Belum ada riwayat
                                                    {{ strtolower($L) }}.</td>
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

    @push('scripts')
        <script>
            $(document).ready(function() {
                @if (session('success'))
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: '{{ session('success') }}',
                        confirmButtonColor: '#4f46e5',
                        timer: 3000
                    });
                @endif

                @if (session('error'))
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops!',
                        text: '{{ session('error') }}',
                        confirmButtonColor: '#f1416c'
                    });
                @endif

                // Animasi Loading saat Buka Shift
                $('#formOpenShift').on('submit', function() {
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Sedang mengatur setup harian dan membuka laci kasir.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                });
            });

            // Logika Tutup Shift
            function confirmClose() {
                Swal.fire({
                    title: "Yakin tutup {{ strtolower($L) }}?",
                    text: "Pastikan uang fisik yang dihitung sudah benar. Kalau tak sengaja tertutup, owner/admin bisa membukanya kembali (undo) di hari yang sama.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya, Tutup Shift!",
                    cancelButtonText: "Batal",
                    customClass: {
                        confirmButton: "btn btn-danger",
                        cancelButton: "btn btn-secondary"
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Menutup Shift...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        // .submit() programatik TIDAK memicu event 'submit', jadi pembersih ribuan
                        // (_number_format) tak jalan. Bersihkan manual dulu: "400.000" -> "400000".
                        var _f = document.getElementById('formCloseShift');
                        var _ac = _f.querySelector('[name="actual_cash"]');
                        if (_ac) _ac.value = String((window.rawNum ? window.rawNum(_ac.value) : Number(String(_ac.value).replace(/[^\d]/g, ''))) || 0);
                        _f.submit();
                    }
                });
            }

            // Koreksi Uang Modal Laci shift berjalan — memakai modal yang sama dengan
            // "Buka Kembali" supaya keduanya meminta ALASAN dengan cara yang sama.
            document.addEventListener('click', function (e) {
                var a = e.target.closest('.js-edit-modal');
                if (!a) return;
                e.preventDefault();
                if (window.moodaModalAlasan) {
                    window.moodaModalAlasan({
                        url: '{{ url('admin/shifts') }}/' + a.getAttribute('data-id') + '/modal',
                        judul: 'Koreksi Modal Laci',
                        keterangan: 'Memperbaiki uang modal laci (kembalian + pengeluaran) shift '
                            + (a.getAttribute('data-name') || 'kasir')
                            + '. Angka “uang fisik seharusnya” ikut menyesuaikan saat shift ditutup nanti.',
                        tampilModal: true,
                        nilaiModal: a.getAttribute('data-modal') || '',
                        tombol: 'Simpan Koreksi',
                    });
                }
            });
        </script>
    @endpush

{{-- Modal alasan: dipakai dua tindakan yang mengubah angka uang shift.
     Alasannya wajib karena keduanya tercatat di Log Activity, dan catatan tanpa
     sebab tidak menolong siapa pun saat laporan kasir diperiksa ulang. --}}
<div class="modal fade" id="m-alasan-shift" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="f-alasan-shift">
        @csrf
        <div class="modal-header py-4">
          <h3 class="modal-title fs-5 fw-bold" id="as-judul">Alasan</h3>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-light-warning border border-warning fs-8 py-3" id="as-keterangan"></div>
          <div class="mb-3 d-none" id="as-wrap-modal">
            <label class="form-label fw-semibold fs-8 required">Uang Modal Laci (Rp)</label>
            <input type="text" name="starting_cash" id="as-modal" class="form-control form-control-solid">
          </div>
          <div>
            <label class="form-label fw-semibold fs-8 required">Alasan</label>
            <input type="text" name="alasan" id="as-alasan" class="form-control form-control-solid"
                   maxlength="255" required placeholder="mis. kasir salah input modal saat buka shift">
            <div class="fs-9 text-muted mt-1">Tersimpan di Log Activity bersama nilai lama &amp; barunya.</div>
          </div>
        </div>
        <div class="modal-footer py-3">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary fw-bold" id="as-simpan">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const modalEl = document.getElementById('m-alasan-shift');
  const form    = document.getElementById('f-alasan-shift');
  if (!modalEl || !form) return;

  window.moodaModalAlasan = function ({ url, judul, keterangan, tampilModal, nilaiModal, tombol }) {
    form.action = url;
    document.getElementById('as-judul').textContent = judul;
    document.getElementById('as-keterangan').innerHTML = keterangan;
    document.getElementById('as-alasan').value = '';
    document.getElementById('as-simpan').textContent = tombol;

    const wrap = document.getElementById('as-wrap-modal');
    wrap.classList.toggle('d-none', !tampilModal);
    const inp = document.getElementById('as-modal');
    inp.required = !!tampilModal;
    inp.value = tampilModal ? nilaiModal : '';

    new bootstrap.Modal(modalEl).show();
  };

  document.addEventListener('click', function (e) {
    const bk = e.target.closest('.js-buka-kembali');
    if (bk) {
      window.moodaModalAlasan({
        url: bk.dataset.url,
        judul: 'Buka Kembali Shift',
        keterangan: 'Shift <b>' + (bk.dataset.kasir || '-') + '</b> yang ditutup pukul ' + bk.dataset.jam +
                    ' akan dibuka lagi. Uang aktual <b>Rp ' + bk.dataset.aktual + '</b> beserta angka selisihnya ' +
                    'DIHAPUS dan diisi ulang saat shift ditutup kembali.',
        tampilModal: false,
        tombol: 'Buka Kembali',
      });
      return;
    }

  });
})();
</script>
@endpush
