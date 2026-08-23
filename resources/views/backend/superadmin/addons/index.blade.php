@extends('backend.layout.app')
@section('title', 'Fitur Tambahan (Add-on)')

@section('content')
<div id="kt_app_content" class="app-content flex-column-fluid mt-5">
  <div id="kt_app_content_container" class="app-container container-xxl">

    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- ============ BERIKAN LANGSUNG ============ --}}
    <div class="card card-flush mb-5">
      <div class="card-header pt-5">
        <div>
          <h3 class="card-title fw-bold text-gray-800 mb-0">Berikan Fitur Tambahan</h3>
          <span class="text-muted fs-8">
            Masa berlakunya otomatis disamakan dengan langganan berbayar tenant, agar keduanya habis bersamaan.
          </span>
        </div>
        @if ($menunggu > 0)
          <div class="card-toolbar">
            <span class="badge badge-light-warning fs-7">{{ $menunggu }} pengajuan menunggu</span>
          </div>
        @endif
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('superadmin.addons.beri') }}" class="row g-3 align-items-end">
          @csrf
          <div class="col-12 col-md-5">
            <label class="form-label fs-8">Tenant (ID)</label>
            <input type="number" name="tenant_id" class="form-control form-control-sm form-control-solid" required
                   placeholder="mis. 2 untuk Terra Coffee">
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label fs-8">Modul</label>
            <select name="module" class="form-select form-select-sm form-select-solid" required>
              @foreach ($katalog as $kunci => $it)
                <option value="{{ $kunci }}">{{ $it['label'] }} — Rp {{ number_format($it['harga'], 0, ',', '.') }}/bulan</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-2">
            <button class="btn btn-sm btn-primary w-100">Aktifkan</button>
          </div>
        </form>
      </div>
    </div>

    {{-- ============ DAFTAR ============ --}}
    <div class="card card-flush">
      <div class="card-header pt-5">
        <h3 class="card-title fw-bold text-gray-800 mb-0">Semua Add-on</h3>
        <div class="card-toolbar">
          <form method="GET" class="d-flex gap-2">
            <input type="text" name="cari" value="{{ $cari }}" class="form-control form-control-sm form-control-solid"
                   placeholder="Cari tenant / modul" style="width:220px">
            <button class="btn btn-sm btn-light-primary">Cari</button>
          </form>
        </div>
      </div>
      <div class="card-body pt-3">
        <div class="table-responsive">
          <table class="table table-row-dashed align-middle gs-0 gy-3">
            <thead><tr class="fw-bold text-muted">
              <th>Tenant</th><th>Modul</th><th>Periode</th><th class="text-end">Nominal</th>
              <th>Peran</th><th>Status</th><th class="text-end">Tindakan</th>
            </tr></thead>
            <tbody>
            @forelse ($addons as $a)
              @php
                // Status yang ditampilkan dihitung ulang, bukan disalin dari kolom:
                // baris berstatus 'active' yang tanggalnya sudah lewat akan
                // menyesatkan -- fiturnya sudah mati, tapi tabelnya bilang aktif.
                $benarAktif = $a->aktif();
                [$teks, $warna] = match (true) {
                    $a->status === 'pending'   => ['MENUNGGU', 'warning'],
                    $a->status === 'cancelled' => ['DIBATALKAN', 'secondary'],
                    $benarAktif                => ['AKTIF', 'success'],
                    default                    => ['KEDALUWARSA', 'danger'],
                };
              @endphp
              <tr>
                <td>
                  <div class="fw-bold text-gray-800">{{ $tenants[$a->tenant_id]->name ?? '(tenant ' . $a->tenant_id . ')' }}</div>
                  <div class="fs-9 text-muted">ID {{ $a->tenant_id }}</div>
                </td>
                <td>
                  <div class="fw-semibold">{{ $a->label }}</div>
                  <div class="fs-9 text-muted">{{ $a->module }}</div>
                </td>
                <td class="fs-8">
                  {{ $a->starts_at ? $a->starts_at->translatedFormat('d M Y') : '—' }}
                  s/d {{ $a->ends_at ? $a->ends_at->translatedFormat('d M Y') : '—' }}
                  <div class="fs-9 text-muted">{{ $a->months }} bulan</div>
                </td>
                <td class="text-end fw-bold">Rp {{ number_format($a->amount, 0, ',', '.') }}</td>
                <td><span class="badge badge-light-primary text-capitalize fs-9">{{ $a->labelPeran() }}</span></td>
                <td><span class="badge badge-light-{{ $warna }} fs-9">{{ $teks }}</span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-light-primary" data-bs-toggle="collapse"
                          data-bs-target="#ubah{{ $a->id }}">Atur</button>
                  @if ($a->status !== 'cancelled')
                    <form method="POST" action="{{ route('superadmin.addons.batalkan', $a->id) }}" class="d-inline"
                          onsubmit="return confirm('Batalkan add-on ini? Fiturnya akan langsung tertutup.')">
                      @csrf
                      <button class="btn btn-sm btn-light-danger">Batalkan</button>
                    </form>
                  @endif
                </td>
              </tr>
              <tr class="collapse" id="ubah{{ $a->id }}">
                <td colspan="7" class="bg-light-primary">
                  <form method="POST" action="{{ route('superadmin.addons.aktifkan', $a->id) }}" class="row g-2 align-items-end py-2">
                    @csrf
                    <div class="col-6 col-md-2">
                      <label class="form-label fs-9">Harga/bulan</label>
                      <input type="number" name="harga" value="{{ (int) $a->price_per_month }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6 col-md-2">
                      <label class="form-label fs-9">Mulai</label>
                      <input type="date" name="mulai" value="{{ optional($a->starts_at)->toDateString() ?: now()->toDateString() }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6 col-md-2">
                      <label class="form-label fs-9">Sampai</label>
                      <input type="date" name="sampai" value="{{ optional($a->ends_at)->toDateString() ?: now()->addMonth()->toDateString() }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label fs-9">Peran yang boleh membuka</label>
                      <input type="text" name="peran" value="{{ implode(',', $a->allowed_roles ?? []) }}"
                             class="form-control form-control-sm" placeholder="kosong = semua peran">
                    </div>
                    <div class="col-12 col-md-3">
                      <button class="btn btn-sm btn-primary w-100">Simpan & aktifkan</button>
                    </div>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-6">Belum ada add-on.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
