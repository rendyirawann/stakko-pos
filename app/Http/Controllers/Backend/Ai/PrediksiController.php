<?php

namespace App\Http\Controllers\Backend\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\Prediksi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

/**
 * AI Prediksi — analisis periode, rekomendasi stok, dan laporan PDF.
 *
 * Angka pada halaman maupun PDF selalu berasal dari Prediksi::data()
 * (dihitung PostgreSQL). Narasi AI adalah lapisan tambahan: bila AI mati atau
 * kuotanya habis, laporan tetap terbit dengan tabel yang benar — hanya tanpa
 * ulasan. Itu pilihan sadar; laporan keuangan tidak boleh bergantung pada
 * ketersediaan layanan pihak ketiga.
 */
class PrediksiController extends Controller
{
    public function index(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $prediksi = new Prediksi($request->user()->tenant);

        return view('backend.ai.prediksi', [
            'dari'   => $dari,
            'sampai' => $sampai,
            'data'   => $prediksi->data($dari, $sampai),
            'siap'   => $prediksi->siap(),
        ]);
    }

    /** Minta narasi AI (dipanggil AJAX supaya halaman tidak menggantung menunggu model). */
    public function analisis(Request $request)
    {
        $request->validate([
            'dari'   => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $kunci = 'ai-prediksi:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($kunci, 4)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terlalu cepat. Tunggu ' . RateLimiter::availableIn($kunci) . ' detik lagi.',
            ], 429);
        }
        RateLimiter::hit($kunci, 60);

        [$dari, $sampai] = $this->rentang($request);
        $prediksi = new Prediksi($request->user()->tenant);

        $data = $prediksi->data($dari, $sampai);
        $narasi = $prediksi->narasi($data, $request->input('catatan'));

        return response()->json([
            'status'  => $narasi['ok'] ? 'success' : 'error',
            'message' => $narasi['ok'] ? $narasi['teks'] : $narasi['error'],
            'periode' => $data['periode']['label'],
        ], $narasi['ok'] ? 200 : 422);
    }

    /**
     * Tafsirkan rentang tanggal dari kalimat bebas ("dari 1 Juli sampai kemarin").
     * Dipisah dari analisis() agar pengguna bisa melihat tanggal yang ditafsirkan
     * dan memperbaikinya sebelum analisis dijalankan.
     */
    public function tafsirTanggal(Request $request)
    {
        $request->validate(['kalimat' => ['required', 'string', 'max:300']]);

        $hasil = (new Prediksi($request->user()->tenant))
            ->tafsirTanggal((string) $request->input('kalimat'));

        return response()->json([
            'status' => 'success',
            'dari'   => $hasil['dari'],
            'sampai' => $hasil['sampai'],
            'sumber' => $hasil['sumber'],
        ]);
    }

    public function pdf(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $tenant = $request->user()->tenant;
        $prediksi = new Prediksi($tenant);
        $data = $prediksi->data($dari, $sampai);

        // Narasi bersifat opsional: PDF harus tetap terbit walau AI gagal.
        $narasi = $request->boolean('dengan_analisis', true)
            ? $prediksi->narasi($data)
            : ['ok' => false, 'teks' => null, 'error' => null];

        $pdf = Pdf::loadView('backend.ai.report-pdf', [
            'tenant' => $tenant,
            'data'   => $data,
            'narasi' => $narasi['ok'] ? $narasi['teks'] : null,
            'catatanNarasi' => $narasi['ok'] ? null : ($narasi['error'] ?: null),
            'dicetak' => Carbon::now(),
        ])->setPaper('a4');

        $nama = 'laporan-' . str($tenant->name)->slug() . '-' . $dari . '-sd-' . $sampai . '.pdf';

        return $pdf->download($nama);
    }

    /**
     * Rentang tanggal permintaan, dibatasi 400 hari.
     * Default: bulan berjalan.
     */
    private function rentang(Request $request): array
    {
        try {
            $dari = $request->filled('dari')
                ? Carbon::parse($request->input('dari'))->startOfDay()
                : Carbon::today()->startOfMonth();
            $sampai = $request->filled('sampai')
                ? Carbon::parse($request->input('sampai'))->startOfDay()
                : Carbon::today();
        } catch (\Throwable) {
            $dari = Carbon::today()->startOfMonth();
            $sampai = Carbon::today();
        }

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        if ($dari->diffInDays($sampai) > 400) {
            $dari = $sampai->copy()->subDays(400);
        }

        return [$dari->toDateString(), $sampai->toDateString()];
    }
}
