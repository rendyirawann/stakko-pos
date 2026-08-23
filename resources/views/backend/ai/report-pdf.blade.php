{{-- Templat laporan PDF (dompdf).
     CSS dibuat sesederhana mungkin: dompdf tidak mendukung flexbox/grid, jadi
     tata letaknya memakai tabel. Semua ANGKA berasal dari $data (hasil hitung
     PostgreSQL), bukan dari teks AI — laporan tetap benar meski narasinya kosong. --}}
@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $k  = $data['keuangan'];
  $adaHpp = (bool) ($k['hpp_tercatat'] ?? false);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { font-size: 10px; color: #1f2937; margin: 0; }
  h1 { font-size: 17px; margin: 0 0 2px; }
  h2 { font-size: 12px; margin: 18px 0 6px; color: #1b84ff; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
  .muted { color: #6b7280; }
  table { width: 100%; border-collapse: collapse; }
  .kpi td { width: 25%; border: 1px solid #e5e7eb; padding: 7px; text-align: center; }
  .kpi .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
  .kpi .nilai { font-size: 13px; font-weight: bold; }
  .data th { background: #f9fafb; border: 1px solid #e5e7eb; padding: 5px; font-size: 9px; text-align: left; }
  .data td { border: 1px solid #e5e7eb; padding: 5px; font-size: 9px; }
  .kanan { text-align: right; }
  .warn { background: #fff7ed; border: 1px solid #fdba74; padding: 7px; font-size: 9px; margin: 10px 0; }
  .kaki { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>

  <table>
    <tr>
      <td>
        <h1>Laporan Analisis Penjualan</h1>
        <div class="muted">{{ $tenant->name }}</div>
      </td>
      <td class="kanan muted" style="vertical-align:top">
        <div><strong>{{ $data['periode']['label'] }}</strong></div>
        <div>{{ $data['periode']['jumlah_hari'] }} hari</div>
        <div>Dicetak {{ $dicetak->translatedFormat('d M Y H:i') }}</div>
      </td>
    </tr>
  </table>

  @unless ($adaHpp)
    <div class="warn">
      <strong>HPP belum tercatat.</strong> Resep menu atau harga beli bahan belum diisi, sehingga
      laba belum dapat dihitung. Angka pada laporan ini adalah <strong>omzet</strong>, bukan keuntungan.
    </div>
  @endunless

  <h2>Ringkasan Keuangan</h2>
  <table class="kpi">
    <tr>
      <td><div class="label">Omzet</div><div class="nilai">{{ $rp($k['omzet'] ?? 0) }}</div>
          <div class="label">{{ number_format($k['jumlah_nota'] ?? 0) }} nota</div></td>
      <td><div class="label">HPP</div><div class="nilai">{{ $rp($k['hpp'] ?? 0) }}</div></td>
      <td><div class="label">Beban</div><div class="nilai">{{ $rp($k['beban'] ?? 0) }}</div></td>
      <td><div class="label">Laba Bersih</div>
          <div class="nilai">{{ $adaHpp ? $rp($k['laba_bersih'] ?? 0) : '—' }}</div>
          <div class="label">{{ $adaHpp && ($k['margin_bersih_persen'] ?? null) !== null ? $k['margin_bersih_persen'] . '% margin' : '' }}</div></td>
    </tr>
  </table>

  <h2>Menu Terlaris</h2>
  <table class="data">
    <thead><tr><th>#</th><th>Menu</th><th class="kanan">Terjual</th><th class="kanan">Omzet</th><th class="kanan">Laba</th></tr></thead>
    <tbody>
      @forelse ($data['terlaris_qty']['menu'] ?? [] as $i => $m)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $m['nama'] }}</td>
          <td class="kanan">{{ number_format($m['terjual']) }}</td>
          <td class="kanan">{{ $rp($m['omzet']) }}</td>
          <td class="kanan">{{ $m['laba'] === null ? '—' : $rp($m['laba']) }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="muted">Tidak ada penjualan pada periode ini.</td></tr>
      @endforelse
    </tbody>
  </table>

  <h2>Perkiraan Kebutuhan Stok</h2>
  <table class="data">
    <thead><tr><th>Bahan</th><th class="kanan">Terpakai</th><th class="kanan">Rata-rata/hari</th>
      <th class="kanan">Sisa stok</th><th class="kanan">Habis dalam</th></tr></thead>
    <tbody>
      @forelse ($data['pemakaian']['bahan'] ?? [] as $b)
        <tr>
          <td>{{ $b['nama'] }}</td>
          <td class="kanan">{{ $b['total_terpakai'] }} {{ $b['satuan'] }}</td>
          <td class="kanan">{{ $b['rata_rata_per_hari'] }}</td>
          <td class="kanan">{{ $b['sisa_stok'] }}</td>
          <td class="kanan">{{ $b['perkiraan_habis_hari'] === null ? '—' : $b['perkiraan_habis_hari'] . ' hari' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="muted">Belum bisa dihitung — resep menu belum diisi.</td></tr>
      @endforelse
    </tbody>
  </table>

  @if (! empty($data['beban']['kategori']))
    <h2>Pengeluaran per Kategori</h2>
    <table class="data">
      <thead><tr><th>Kategori</th><th class="kanan">Transaksi</th><th class="kanan">Total</th></tr></thead>
      <tbody>
        @foreach ($data['beban']['kategori'] as $b)
          <tr><td>{{ $b['kategori'] }}</td>
              <td class="kanan">{{ $b['jumlah_transaksi'] }}</td>
              <td class="kanan">{{ $rp($b['total']) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  @if ($narasi)
    <h2>Ulasan AI</h2>
    {{-- Narasi ditampilkan sebagai teks apa adanya. Sengaja tidak dirender
         sebagai HTML: isinya keluaran model, dan PDF bukan tempat menjalankan
         markup yang tak terpercaya. --}}
    <div style="white-space: pre-wrap; font-size: 9.5px; line-height: 1.5">{{ $narasi }}</div>
  @elseif ($catatanNarasi)
    <h2>Ulasan AI</h2>
    <div class="warn">Ulasan AI tidak tersedia: {{ $catatanNarasi }} Angka pada laporan ini tetap akurat karena dihitung langsung dari database.</div>
  @endif

  <div class="kaki">
    Dihitung langsung dari basis data transaksi. Pesanan yang dibatalkan (void) tidak diikutsertakan.
    Omzet = total nota berstatus dibayar. Laba bersih = omzet &minus; HPP &minus; beban.
  </div>

</body>
</html>
