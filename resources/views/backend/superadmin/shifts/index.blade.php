@extends('backend.layout.app')
@section('title', 'Manajemen Shift')
@section('content')
@php
  $rp  = fn ($n) => $n === null ? '—' : 'Rp ' . number_format((float) $n, 0, ',', '.');
  // start_time tidak di-cast ke tanggal di model Shift, jadi diurai sendiri.
  $tgl = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('d/m/Y H:i') : '—';
@endphp

<div id="kt_app_content" class="app-content flex-column-fluid mt-5">
  <div id="kt_app_content_container" class="app-container container-xxl">

    @if (session('success'))
      <div class="alert alert-success d-flex align-items-center py-3">
        <i class="ki-outline ki-check-circle fs-2 me-2"></i><div>{{ session('success') }}</div>
      </div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger py-3">
        <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="card card-flush">
      <div class="card-header pt-5">
        <div>
          <h3 class="card-title fw-bold fs-4 mb-0">
            <i class="ki-outline ki-time fs-2 text-primary me-2"></i>Manajemen Shift</h3>
          <span class="text-muted fs-8">
            Koreksi <b>modal laci</b> dan <b>uang aktual</b> tanpa menghapus shift-nya —
            riwayat penjualan yang menempel tetap utuh. Angka “seharusnya” &amp; “selisih”
            dihitung ulang otomatis, dan tiap koreksi tercatat di Log Activity.
          </span>
        </div>
        <div class="card-toolbar">
          <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
            <select name="tenant_id" class="form-select form-select-sm form-select-solid" style="width:180px">
              <option value="">Semua tenant</option>
              @foreach ($tenants as $t)
                <option value="{{ $t->id }}" {{ (string) $tenantId === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
              @endforeach
            </select>
            <select name="status" class="form-select form-select-sm form-select-solid" style="width:130px">
              <option value="">Semua status</option>
              <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Berjalan</option>
              <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Ditutup</option>
            </select>
            <select name="selisih" class="form-select form-select-sm form-select-solid" style="width:150px">
              <option value="">Semua selisih</option>
              <option value="ada" {{ $selisih === 'ada' ? 'selected' : '' }}>Hanya yang selisih</option>
            </select>
            <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm form-control-solid" style="width:150px">
            <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm form-control-solid" style="width:150px">
            <button class="btn btn-sm btn-light-primary fw-bold">Filter</button>
          </form>
        </div>
      </div>

      <div class="card-body pt-4">
        <div class="table-responsive">
          <table class="table table-row-bordered align-middle gy-3 mb-0">
            <thead><tr class="fw-bold text-muted bg-light fs-8">
              <th class="ps-4">Shift</th><th>Tenant / Kasir</th><th>Status</th>
              <th class="text-end">Modal</th><th class="text-end">Penjualan Tunai</th>
              <th class="text-end">Seharusnya</th><th class="text-end">Aktual</th>
              <th class="text-end">Selisih</th><th class="text-end pe-4">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($rows as $r)
              @php $beda = $r->difference === null ? null : (float) $r->difference; @endphp
              <tr>
                <td class="ps-4">
                  <div class="fw-bold text-gray-800">#{{ $r->id }}</div>
                  <div class="fs-9 text-muted">{{ $tgl($r->start_time) }}</div>
                </td>
                <td>
                  <div class="fw-bold text-gray-800">{{ $r->tenant?->name ?? '—' }}</div>
                  <div class="fs-9 text-muted">{{ $r->user?->name ?? '—' }}</div>
                </td>
                <td>
                  <span class="badge badge-light-{{ $r->status === 'open' ? 'warning' : 'secondary' }}">
                    {{ $r->status === 'open' ? 'Berjalan' : 'Ditutup' }}</span>
                </td>
                <td class="text-end fw-bold">{{ $rp($r->starting_cash) }}</td>
                <td class="text-end text-muted">{{ $rp($r->cash_sales) }}</td>
                <td class="text-end">{{ $rp($r->expected_cash) }}</td>
                <td class="text-end fw-bold">{{ $rp($r->actual_cash) }}</td>
                <td class="text-end fw-bold">
                  @if ($beda === null)
                    <span class="text-muted">—</span>
                  @elseif (abs($beda) < 0.01)
                    <span class="badge badge-light-success">Pas</span>
                  @else
                    <span class="{{ $beda < 0 ? 'text-danger' : 'text-primary' }}">
                      {{ $beda > 0 ? 'Lebih ' : 'Kurang ' }}{{ $rp(abs($beda)) }}</span>
                  @endif
                </td>
                <td class="text-end pe-4">
                  <button class="btn btn-sm btn-light-primary py-1 px-3 fs-8 js-koreksi"
                          data-shift="{{ json_encode([
                              'id' => $r->id,
                              'tenant' => $r->tenant?->name,
                              'kasir' => $r->user?->name,
                              'tanggal' => $tgl($r->start_time),
                              'status' => $r->status,
                              'modal' => (float) $r->starting_cash,
                              'aktual' => $r->actual_cash === null ? null : (float) $r->actual_cash,
                          ]) }}">Koreksi</button>
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-center text-muted py-10">Tidak ada shift yang cocok dengan filter ini.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Modal koreksi --}}
<div class="modal fade" id="m-koreksi" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="f-koreksi">
        @csrf @method('PUT')
        <div class="modal-header py-4">
          <h3 class="modal-title fs-5 fw-bold">Koreksi Shift <span id="k-judul"></span></h3>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-light-warning border border-warning fs-8 py-3">
            Angka <b>seharusnya</b> dan <b>selisih</b> tidak diisi di sini — keduanya dihitung
            ulang dari modal + penjualan tunai − pengeluaran shift ini.
          </div>
          <div class="mb-3 fs-8 text-muted" id="k-info"></div>
          <div class="mb-3">
            <label class="form-label fw-semibold fs-8 required">Modal Laci</label>
            <input type="text" name="starting_cash" id="k-modal" class="form-control form-control-solid" required>
          </div>
          <div class="mb-3" id="k-wrap-aktual">
            <label class="form-label fw-semibold fs-8">Uang Aktual (hasil hitung fisik)</label>
            <input type="text" name="actual_cash" id="k-aktual" class="form-control form-control-solid">
            <div class="fs-9 text-muted mt-1">Kosongkan bila belum dihitung.</div>
          </div>
          <div>
            <label class="form-label fw-semibold fs-8 required">Alasan Koreksi</label>
            <input type="text" name="alasan" id="k-alasan" class="form-control form-control-solid"
                   maxlength="255" required placeholder="mis. salah input modal saat buka shift">
            <div class="fs-9 text-muted mt-1">Tersimpan di Log Activity bersama nilai lama &amp; barunya.</div>
          </div>
        </div>
        <div class="modal-footer py-3">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary fw-bold">Simpan Koreksi</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const modal = document.getElementById('m-koreksi');
  const form  = document.getElementById('f-koreksi');
  const DASAR = @json(url('/admin/superadmin/shifts'));

  document.addEventListener('click', function (e) {
    const b = e.target.closest('.js-koreksi');
    if (!b) return;
    const d = JSON.parse(b.dataset.shift);

    form.action = DASAR + '/' + d.id;
    document.getElementById('k-judul').textContent = '#' + d.id;
    document.getElementById('k-info').innerHTML =
      '<b>' + (d.tenant || '-') + '</b> · kasir ' + (d.kasir || '-') + ' · ' + (d.tanggal || '-') +
      ' · status ' + (d.status === 'open' ? 'berjalan' : 'ditutup');
    document.getElementById('k-modal').value = d.modal;
    document.getElementById('k-aktual').value = d.aktual === null ? '' : d.aktual;

    // Shift yang masih berjalan belum punya uang aktual — kolomnya disembunyikan
    // supaya tidak ada angka penutup yang diisi sebelum waktunya.
    document.getElementById('k-wrap-aktual').classList.toggle('d-none', d.status !== 'closed');
    document.getElementById('k-alasan').value = '';

    new bootstrap.Modal(modal).show();
  });
})();
</script>
@endpush
