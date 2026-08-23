<?php

namespace App\Http\Controllers\Backend\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\Assistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * AI Assistant — chat analitik atas data tenant sendiri.
 *
 * Semua akses dibatasi ke tenant pengguna. `AiConversation` memakai
 * BelongsToTenant sehingga query otomatis ter-scope, tetapi pemeriksaan
 * pemilik percakapan tetap dilakukan eksplisit: satu kasir tidak boleh membuka
 * percakapan pemilik toko, meski tenant-nya sama.
 */
class AssistantController extends Controller
{
    public function index(Request $request)
    {
        $obrolan = AiConversation::where('user_id', $request->user()->id)
            ->where('kind', 'assistant')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $aktif = $obrolan->first();

        return view('backend.ai.assistant', [
            'daftar'  => $obrolan,
            'aktif'   => $aktif,
            'pesan'   => $aktif ? $aktif->messages : collect(),
            'siap'    => (new Assistant($request->user()->tenant, (string) $request->user()->id))->siap(),
            'contoh'  => $this->contohPertanyaan(),
        ]);
    }

    public function show(Request $request, string $uuid)
    {
        $obrolan = $this->milikSaya($request, $uuid);

        return view('backend.ai.assistant', [
            'daftar' => AiConversation::where('user_id', $request->user()->id)
                ->where('kind', 'assistant')
                ->orderByDesc('last_message_at')->orderByDesc('id')->limit(30)->get(),
            'aktif'  => $obrolan,
            'pesan'  => $obrolan->messages,
            'siap'   => (new Assistant($request->user()->tenant, (string) $request->user()->id))->siap(),
            'contoh' => $this->contohPertanyaan(),
        ]);
    }

    public function kirim(Request $request)
    {
        $request->validate([
            'pertanyaan' => ['required', 'string', 'min:3', 'max:1000'],
            'uuid'       => ['nullable', 'string'],
        ], [
            'pertanyaan.required' => 'Tulis pertanyaannya dulu.',
            'pertanyaan.max'      => 'Pertanyaan terlalu panjang (maksimal 1000 karakter).',
        ]);

        $user = $request->user();

        // Pagar per pengguna: mencegah satu orang menekan kirim berulang dan
        // menghabiskan kuota harian seluruh toko dalam beberapa detik.
        $kunci = 'ai-tanya:' . $user->id;
        $maks = (int) config('ai.batas.pesan_per_menit_per_user');
        if (RateLimiter::tooManyAttempts($kunci, $maks)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terlalu cepat. Tunggu ' . RateLimiter::availableIn($kunci) . ' detik lagi.',
            ], 429);
        }
        RateLimiter::hit($kunci, 60);

        $obrolan = $request->filled('uuid')
            ? $this->milikSaya($request, (string) $request->input('uuid'))
            : AiConversation::create([
                'tenant_id' => $user->tenant_id,
                'user_id'   => $user->id,
                'kind'      => 'assistant',
                'title'     => mb_substr($request->input('pertanyaan'), 0, 60),
            ]);

        $asisten = new Assistant($user->tenant, (string) $user->id);
        $hasil = $asisten->tanya($obrolan, (string) $request->input('pertanyaan'));

        return response()->json([
            'status'  => $hasil['ok'] ? 'success' : 'error',
            'message' => $hasil['ok'] ? $hasil['jawaban'] : $hasil['error'],
            'sumber'  => $hasil['sumber'],
            'brain'   => $hasil['brain'],
            'ms'      => $hasil['ms'],
            'uuid'    => $obrolan->uuid,
        ], $hasil['ok'] ? 200 : 422);
    }

    public function hapus(Request $request, string $uuid)
    {
        $this->milikSaya($request, $uuid)->delete();

        return redirect()->route('ai.assistant.index')->with('success', 'Percakapan dihapus.');
    }

    /** Percakapan milik pengguna ini sendiri; selain itu 404, bukan 403 — jangan bocorkan keberadaannya. */
    private function milikSaya(Request $request, string $uuid): AiConversation
    {
        return AiConversation::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->where('kind', 'assistant')
            ->firstOrFail();
    }

    private function contohPertanyaan(): array
    {
        return [
            'Menu apa saja yang tidak bisa dijual sekarang?',
            'Berapa laba bersih bulan ini?',
            'Menu apa yang paling laris 30 hari terakhir?',
            'Bahan apa yang perlu segera dibeli?',
            'Berapa HPP dan margin menu andalan saya?',
            'Bandingkan omzet minggu ini dengan minggu lalu.',
        ];
    }
}
