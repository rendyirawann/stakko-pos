{{-- Satu gelembung pesan pada riwayat yang dirender server. --}}
@php $kanan = $m->role === 'user'; @endphp
<div class="d-flex mb-4 {{ $kanan ? 'justify-content-end' : '' }}">
  <div class="p-4 rounded {{ $kanan ? 'bg-light-primary' : 'bg-light' }}" style="max-width:85%">
    <div class="fs-7 text-gray-800" style="white-space:pre-wrap">{{ $m->content }}</div>
    @if (! $kanan && ! empty($m->sources))
      <div class="mt-2 fs-9">
        @foreach (collect($m->sources)->where('jenis', 'database')->pluck('fungsi')->unique() as $f)
          <span class="badge badge-light-primary me-1">data: {{ $f }}</span>
        @endforeach
        @foreach (collect($m->sources)->where('jenis', 'web') as $w)
          <a href="{{ $w['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer"
             class="badge badge-light-info me-1">{{ \Illuminate\Support\Str::limit($w['judul'] ?? 'web', 30) }}</a>
        @endforeach
      </div>
    @endif
  </div>
</div>
