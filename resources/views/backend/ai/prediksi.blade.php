@extends('backend.layout.app')
@section('title', 'AI Prediksi')

@section('content')
@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $k  = $data['keuangan'];
  $adaHpp = (bool) ($k['hpp_tercatat'] ?? false);
@endphp

<div id="kt_app_content" class="app-content flex-column-fluid mt-5">
  <div id="kt_app_content_container" class="app-container container-xxl">

    {{-- ============ PEMILIH PERIODE ============ --}}
    <div class="card card-flush mb-5">
      <div class="card-body py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
          <div>
            <h3 class="fw-bold fs-4 mb-1">
              <i class="ki-outline ki-chart-line-up fs-2 text-primary me-2"></i>AI Prediksi
            </h3>
            <div class="fs-8 text-muted">Analisis periode, rekomendasi stok, dan laporan PDF.</div>
          </div>
          <form method="GET" class="d-flex flex-wrap align-items-end gap-2">
            <div>
              <label class="form-label fs-9 mb-1">Dari</label>
              <input type="date" name="dari" value="{{ $dari }}" class="form-control form-control-sm form-control-solid" style="width:150px">
            </div>
            <div>
              <label class="form-label fs-9 mb-1">Sampai</label>
              <input type="date" name="sampai" value="{{ $sampai }}" class="form-control form-control-sm form-control-solid" style="width:150px">
            </div>
            <button class="btn btn-sm btn-primary">Tampilkan</button>
            <a href="{{ route('ai.prediksi.pdf', ['dari' => $dari, 'sampai' => $sampai]) }}"
               class="btn btn-sm btn-light-danger">
              <i class="ki-outline ki-file-down fs-4"></i> Unduh PDF</a>
          </form>
        </div>

        {{-- Tanggal dari kalimat bebas: pengguna sering berpikir "bulan lalu",
             bukan "2026-07-01". Hasil tafsirannya ditampilkan agar bisa dikoreksi
             sebelum dipakai, bukan langsung dijalankan. --}}
        <div class="separator my-4"></div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="fs-8 text-muted">Atau tulis periodenya:</span>
          <input type="text" id="kalimatTanggal" class="form-control form-control-sm form-control-solid"
                 style="max-width:320px" placeholder="mis. dari 1 Juli sampai kemarin">
          <button type="button" id="btnTafsir" class="btn btn-sm btn-light-primary">Tafsirkan</button>
          <span id="hasilTafsir" class="fs-8 text-muted"></span>
        </div>
      </div>
    </div>

    @if (! $siap)
      <div class="alert alert-warning d-flex align-items-center mb-5">
        <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
        <div><h4 class="mb-1">Analisis AI belum aktif</h4>
          <span class="text-gray-700">Tabel di bawah tetap akurat karena dihitung langsung dari database. Yang belum tersedia hanya ulasan AI-nya.</span></div>
      </div>
    @endif

    @unless ($adaHpp)
      <div class="alert alert-primary d-flex align-items-center mb-5">
        <i class="ki-outline ki-information-5 fs-2x text-primary me-3"></i>
        <div><h4 class="mb-1">HPP belum tercatat</h4>
          <span class="text-gray-700">Resep menu atau harga beli bahan belum diisi, jadi <b>laba belum bisa dihitung</b> —
          angka di bawah adalah omzet, bukan keuntungan. Isi resep di menu Data Master agar analisis laba tersedia.</span></div>
      </div>
    @endunless

    {{-- ============ ANGKA UTAMA ============ --}}
    <div class="row g-4 mb-5">
      @foreach ([
        ['Omzet', $k['omzet'] ?? 0, 'primary'],
        ['HPP', $k['hpp'] ?? 0, 'warning'],
        ['Beban', $k['beban'] ?? 0, 'danger'],
        ['Laba Bersih', $adaHpp ? ($k['laba_bersih'] ?? 0) : null, 'success'],
      ] as [$label, $nilai, $warna])
        <div class="col-6 col-xl-3">
          <div class="card card-flush h-100"><div class="card-body">
            <div class="text-muted fs-8 fw-bold text-uppercase">{{ $label }}</div>
            <div class="fs-3 fw-bold text-{{ $warna }}">
              {{ $nilai === null ? '—' : $rp($nilai) }}
            </div>
            <div class="fs-9 text-muted">
              {{ $label === 'Omzet' ? number_format($k['jumlah_nota'] ?? 0) . ' nota' : ($nilai === null ? 'butuh HPP' : '') }}
            </div>
          </div></div>
        </div>
      @endforeach
    </div>

    {{-- ============ ULASAN AI ============ --}}
    <div class="card card-flush mb-5">
      <div class="card-header pt-4 pb-0 border-0">
        <h3 class="card-title fw-bold fs-5 mb-0">Ulasan AI</h3>
        <div class="card-toolbar">
          <button id="btnAnalisis" class="btn btn-sm btn-primary" {{ $siap ? '' : 'disabled' }}>
            <span class="indicator-label"><i class="ki-outline ki-magic-wand fs-4"></i> Analisis periode ini</span>
            <span class="indicator-progress">Menganalisis… <span class="spinner-border spinner-border-sm ms-2"></span></span>
          </button>
        </div>
      </div>
      <div class="card-body pt-3">
        <input type="text" id="catatanKhusus" class="form-control form-control-sm form-control-solid mb-3"
               maxlength="500" placeholder="Permintaan khusus (opsional), mis. fokus pada menu minuman">
        <div id="hasilAnalisis" class="text-gray-800 fs-7">
          <span class="text-muted">Belum dianalisis. Tekan tombol di atas.</span>
        </div>
      </div>
    </div>

    {{-- ============ TABEL ============ --}}
    <div class="row g-5">
      <div class="col-12 col-xl-6">
        <div class="card card-flush h-100">
          <div class="card-header pt-4 pb-0 border-0"><h3 class="card-title fw-bold fs-5 mb-0">Menu Terlaris</h3></div>
          <div class="card-body pt-3">
            <div class="table-responsive">
              <table class="table table-row-bordered table-sm align-middle mb-0">
                <thead><tr><th class="fs-8">Menu</th><th class="fs-8 text-end">Terjual</th>
                  <th class="fs-8 text-end">Omzet</th><th class="fs-8 text-end">Laba</th></tr></thead>
                <tbody>
                @forelse ($data['terlaris_qty']['menu'] ?? [] as $m)
                  <tr>
                    <td class="fs-8">{{ $m['nama'] }}</td>
                    <td class="fs-8 text-end">{{ number_format($m['terjual']) }}</td>
                    <td class="fs-8 text-end">{{ $rp($m['omzet']) }}</td>
                    <td class="fs-8 text-end">{{ $m['laba'] === null ? '—' : $rp($m['laba']) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-muted fs-8 text-center py-4">Tidak ada penjualan pada periode ini.</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="card card-flush h-100">
          <div class="card-header pt-4 pb-0 border-0"><h3 class="card-title fw-bold fs-5 mb-0">Perkiraan Stok Habis</h3></div>
          <div class="card-body pt-3">
            <div class="table-responsive">
              <table class="table table-row-bordered table-sm align-middle mb-0">
                <thead><tr><th class="fs-8">Bahan</th><th class="fs-8 text-end">Pakai/hari</th>
                  <th class="fs-8 text-end">Sisa</th><th class="fs-8 text-end">Habis dalam</th></tr></thead>
                <tbody>
                @forelse ($data['pemakaian']['bahan'] ?? [] as $b)
                  <tr>
                    <td class="fs-8">{{ $b['nama'] }}</td>
                    <td class="fs-8 text-end">{{ $b['rata_rata_per_hari'] }} {{ $b['satuan'] }}</td>
                    <td class="fs-8 text-end">{{ $b['sisa_stok'] }}</td>
                    <td class="fs-8 text-end">
                      @if ($b['perkiraan_habis_hari'] === null)
                        —
                      @else
                        <span class="badge badge-light-{{ $b['perkiraan_habis_hari'] < 3 ? 'danger' : ($b['perkiraan_habis_hari'] < 7 ? 'warning' : 'success') }}">
                          {{ $b['perkiraan_habis_hari'] }} hari</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-muted fs-8 text-center py-4">
                    Pemakaian bahan belum bisa dihitung — resep menu belum diisi.</td></tr>
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
@endsection

@push('scripts')
<script>
(function () {
  const tokenCsrf = '{{ csrf_token() }}';
  const dari = '{{ $dari }}', sampai = '{{ $sampai }}';

  const escapeHtml = (t) => { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };

  // Sama seperti di AI Assistant: keluaran model di-escape lebih dulu, lalu
  // hanya pola markdown yang aman diaktifkan.
  function render(teks) {
    let h = escapeHtml(teks);
    h = h.replace(/^## (.*)$/gm, '<div class="fw-bold fs-5 mt-4 mb-2 text-primary">$1</div>');
    h = h.replace(/^### (.*)$/gm, '<div class="fw-bold fs-6 mt-3">$1</div>');
    h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    h = h.replace(/^[-*] (.*)$/gm, '<li>$1</li>');
    h = h.replace(/(<li>[\s\S]*?<\/li>)/g, '<ul class="mb-2">$1</ul>');
    return h.replace(/\n{2,}/g, '<br>').replace(/\n/g, '<br>');
  }

  // ---- Tafsir tanggal dari kalimat bebas ----
  const btnTafsir = document.getElementById('btnTafsir');
  btnTafsir?.addEventListener('click', function () {
    const kalimat = document.getElementById('kalimatTanggal').value.trim();
    const info = document.getElementById('hasilTafsir');
    if (kalimat.length < 3) { info.textContent = 'Tulis periodenya dulu.'; return; }
    info.textContent = 'Menafsirkan…';

    const fd = new FormData();
    fd.append('_token', tokenCsrf);
    fd.append('kalimat', kalimat);
    fetch('{{ route('ai.prediksi.tafsir') }}', { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
      .then(r => r.json())
      .then(j => {
        if (j.status === 'success') {
          document.querySelector('[name=dari]').value = j.dari;
          document.querySelector('[name=sampai]').value = j.sampai;
          info.innerHTML = `<span class="text-success">${j.dari} s/d ${j.sampai}</span> — tekan <b>Tampilkan</b>`;
        } else {
          info.textContent = 'Gagal menafsirkan.';
        }
      })
      .catch(() => { info.textContent = 'Gagal menghubungi server.'; });
  });

  // ---- Minta ulasan AI ----
  const btn = document.getElementById('btnAnalisis');
  btn?.addEventListener('click', function () {
    const kotak = document.getElementById('hasilAnalisis');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    kotak.innerHTML = '<span class="text-muted">Menganalisis data periode ini…</span>';

    const fd = new FormData();
    fd.append('_token', tokenCsrf);
    fd.append('dari', dari);
    fd.append('sampai', sampai);
    fd.append('catatan', document.getElementById('catatanKhusus').value);

    fetch('{{ route('ai.prediksi.analisis') }}', { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
      .then(r => r.json().then(j => ({ ok: r.ok, j })))
      .then(({ ok, j }) => {
        kotak.innerHTML = (ok && j.status === 'success')
          ? render(j.message)
          : '<div class="alert alert-warning mb-0">' + escapeHtml(j.message || 'Gagal menganalisis.') + '</div>';
      })
      .catch(() => { kotak.innerHTML = '<div class="alert alert-warning mb-0">Gagal menghubungi server.</div>'; })
      .finally(() => { btn.removeAttribute('data-kt-indicator'); btn.disabled = false; });
  });
})();
</script>
@endpush
