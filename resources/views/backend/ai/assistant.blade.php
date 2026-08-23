@extends('backend.layout.app')
@section('title', 'AI Assistant')

@section('content')
<div id="kt_app_content" class="app-content flex-column-fluid mt-5">
  <div id="kt_app_content_container" class="app-container container-xxl">

    @if (! $siap)
      <div class="alert alert-warning d-flex align-items-center mb-5">
        <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
        <div>
          <h4 class="mb-1">Fitur AI belum aktif</h4>
          <span class="text-gray-700">Kunci API penyedia AI belum dipasang di server. Hubungi administrator.</span>
        </div>
      </div>
    @endif

    <div class="row g-5">
      {{-- ============ DAFTAR PERCAKAPAN ============ --}}
      <div class="col-12 col-lg-3">
        <div class="card card-flush h-100">
          <div class="card-header pt-4 pb-0 border-0">
            <h3 class="card-title fw-bold fs-5 mb-0">Percakapan</h3>
            <div class="card-toolbar">
              <a href="{{ route('ai.assistant.index') }}" class="btn btn-sm btn-light-primary">
                <i class="ki-outline ki-plus fs-4"></i> Baru</a>
            </div>
          </div>
          <div class="card-body pt-3 px-3" style="max-height:70vh; overflow-y:auto">
            @forelse ($daftar as $d)
              <a href="{{ route('ai.assistant.show', $d->uuid) }}"
                 class="d-block p-3 mb-2 rounded text-decoration-none {{ $aktif && $aktif->id === $d->id ? 'bg-light-primary border border-primary' : 'bg-light' }}">
                <div class="fw-semibold text-gray-800 fs-7 text-truncate">{{ $d->title ?: 'Tanpa judul' }}</div>
                <div class="fs-9 text-muted">{{ optional($d->last_message_at ?? $d->created_at)->diffForHumans() }}</div>
              </a>
            @empty
              <div class="text-muted fs-8 text-center py-4">Belum ada percakapan.</div>
            @endforelse
          </div>
        </div>
      </div>

      {{-- ============ RUANG CHAT ============ --}}
      <div class="col-12 col-lg-9">
        <div class="card card-flush">
          <div class="card-header pt-4 pb-0 border-0">
            <div>
              <h3 class="card-title fw-bold fs-4 mb-0">
                <i class="ki-outline ki-messages fs-2 text-success me-2"></i>AI Assistant
              </h3>
              <div class="fs-8 text-muted mt-1">
                Menjawab dari data toko Anda sendiri. Hanya bisa membaca — tidak mengubah data apa pun.
              </div>
            </div>
            @if ($aktif)
              <div class="card-toolbar">
                <form method="POST" action="{{ route('ai.assistant.hapus', $aktif->uuid) }}"
                      onsubmit="return confirm('Hapus percakapan ini?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-light-danger"><i class="ki-outline ki-trash fs-5"></i></button>
                </form>
              </div>
            @endif
          </div>

          <div class="card-body pt-4">
            <div id="ruangChat" style="min-height:45vh; max-height:55vh; overflow-y:auto">
              @forelse ($pesan as $m)
                @include('backend.ai._bubble', ['m' => $m])
              @empty
                <div class="text-center py-10">
                  <i class="ki-outline ki-messages fs-3x text-success opacity-50"></i>
                  <div class="fw-bold fs-5 mt-3 text-gray-800">Tanyakan apa saja tentang toko Anda</div>
                  <div class="fs-8 text-muted mb-5">Contoh pertanyaan:</div>
                  <div class="d-flex flex-wrap justify-content-center gap-2">
                    @foreach ($contoh as $c)
                      <button type="button" class="btn btn-sm btn-light-primary contohBtn">{{ $c }}</button>
                    @endforeach
                  </div>
                </div>
              @endforelse
            </div>

            <form id="formTanya" class="mt-4" autocomplete="off">
              @csrf
              <input type="hidden" name="uuid" id="uuidObrolan" value="{{ $aktif->uuid ?? '' }}">
              <div class="d-flex gap-2">
                <input type="text" name="pertanyaan" id="inputTanya" class="form-control form-control-solid"
                       placeholder="Contoh: berapa laba bersih bulan ini?" maxlength="1000" {{ $siap ? '' : 'disabled' }}>
                <button type="submit" id="btnKirim" class="btn btn-primary" {{ $siap ? '' : 'disabled' }}>
                  <span class="indicator-label"><i class="ki-outline ki-send fs-3"></i></span>
                  <span class="indicator-progress">Berpikir… <span class="spinner-border spinner-border-sm ms-2"></span></span>
                </button>
              </div>
              <div class="fs-9 text-muted mt-2">
                Angka diambil langsung dari database, bukan perkiraan. Setiap jawaban mencantumkan sumbernya.
              </div>
            </form>
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
  const form   = document.getElementById('formTanya');
  const input  = document.getElementById('inputTanya');
  const btn    = document.getElementById('btnKirim');
  const ruang  = document.getElementById('ruangChat');
  const uuidEl = document.getElementById('uuidObrolan');
  if (!form) return;

  const gulirBawah = () => { ruang.scrollTop = ruang.scrollHeight; };
  gulirBawah();

  const escapeHtml = (t) => {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
  };

  // Penyusun markdown seadanya: hanya tebal, judul, dan tabel. Sengaja tidak
  // memakai pustaka markdown penuh — keluaran model adalah teks tak terpercaya,
  // jadi semuanya di-escape lebih dulu dan hanya pola aman yang dihidupkan.
  function render(teks) {
    let h = escapeHtml(teks);
    h = h.replace(/^### (.*)$/gm, '<div class="fw-bold fs-6 mt-3">$1</div>');
    h = h.replace(/^## (.*)$/gm, '<div class="fw-bold fs-5 mt-3">$1</div>');
    h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Tabel markdown -> tabel HTML.
    h = h.replace(/((?:^\|.*\|\s*$\n?)+)/gm, (blok) => {
      const baris = blok.trim().split('\n').filter(b => b.trim());
      if (baris.length < 2) return blok;
      const sel = (b) => b.trim().replace(/^\||\|$/g, '').split('|').map(x => x.trim());
      const kepala = sel(baris[0]);
      const isi = baris.slice(2).map(sel);
      let t = '<div class="table-responsive my-3"><table class="table table-row-bordered table-sm align-middle mb-0"><thead><tr>';
      kepala.forEach(k => t += `<th class="fw-bold fs-8">${k}</th>`);
      t += '</tr></thead><tbody>';
      isi.forEach(r => { t += '<tr>'; r.forEach(c => t += `<td class="fs-8">${c}</td>`); t += '</tr>'; });
      return t + '</tbody></table></div>';
    });

    return h.replace(/\n{2,}/g, '<br><br>').replace(/\n/g, '<br>');
  }

  function bubble(peran, isi, sumber, brain) {
    const kanan = peran === 'user';
    const el = document.createElement('div');
    el.className = 'd-flex mb-4 ' + (kanan ? 'justify-content-end' : '');
    let jejak = '';
    if (!kanan && sumber && sumber.length) {
      const db = sumber.filter(s => s.jenis === 'database').map(s => s.fungsi);
      const web = sumber.filter(s => s.jenis === 'web');
      const bagian = [];
      if (db.length) bagian.push('<span class="badge badge-light-primary me-1">data: ' + db.join(', ') + '</span>');
      web.forEach(w => bagian.push(`<a href="${w.url}" target="_blank" rel="noopener noreferrer" class="badge badge-light-info me-1">${escapeHtml(w.judul || 'web')}</a>`));
      if (bagian.length) jejak = '<div class="mt-2 fs-9">' + bagian.join('') + '</div>';
    }
    el.innerHTML = `<div class="p-4 rounded ${kanan ? 'bg-light-primary' : 'bg-light'}" style="max-width:85%">
        <div class="fs-7 text-gray-800">${kanan ? escapeHtml(isi) : render(isi)}</div>${jejak}</div>`;
    ruang.appendChild(el);
    gulirBawah();
  }

  function kirim(pertanyaan) {
    if (!pertanyaan || pertanyaan.trim().length < 3) return;
    // Kosongkan tampilan sambutan pada pertanyaan pertama.
    if (ruang.querySelector('.contohBtn')) ruang.innerHTML = '';

    bubble('user', pertanyaan);
    input.value = '';
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('_token', form.querySelector('[name=_token]').value);
    fd.append('pertanyaan', pertanyaan);
    fd.append('uuid', uuidEl.value);

    fetch('{{ route('ai.assistant.kirim') }}', {
      method: 'POST', body: fd, headers: { 'Accept': 'application/json' },
    })
      .then(r => r.json().then(j => ({ ok: r.ok, j })))
      .then(({ ok, j }) => {
        if (ok && j.status === 'success') {
          bubble('assistant', j.message, j.sumber, j.brain);
          if (j.uuid && !uuidEl.value) uuidEl.value = j.uuid;
        } else {
          bubble('assistant', '⚠️ ' + (j.message || 'Gagal memproses pertanyaan.'));
        }
      })
      .catch(() => bubble('assistant', '⚠️ Koneksi ke server gagal. Coba lagi.'))
      .finally(() => { btn.removeAttribute('data-kt-indicator'); btn.disabled = false; input.focus(); });
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); kirim(input.value); });
  ruang.addEventListener('click', (e) => {
    if (e.target.classList.contains('contohBtn')) kirim(e.target.textContent.trim());
  });
})();
</script>
@endpush
