<?php

namespace App\Http\Controllers\Backend\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Shift;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MANAJEMEN SHIFT — koreksi modal & uang aktual, KHUSUS SUPERADMIN.
 *
 * Dibuat karena satu-satunya jalan membetulkan salah ketik modal/aktual sebelum
 * ini adalah menghapus shift-nya, dan itu ikut membuang riwayat penjualan yang
 * menempel padanya. Di sini shiftnya dipertahankan, angkanya saja yang dibetulkan.
 *
 * Dua hal yang dijaga ketat:
 *
 *  1. Angka turunan TIDAK diterima dari formulir. `expected_cash` dan
 *     `difference` selalu dihitung ulang dengan rumus yang sama seperti saat
 *     shift ditutup (modal + penjualan tunai - pengeluaran). Kalau ikut dikirim
 *     dari layar, sekali salah ketik laporan kasir jadi tidak konsisten selamanya.
 *  2. Setiap perubahan dicatat ke log aktivitas beserta nilai lama & barunya —
 *     ini menyentuh uang, jadi harus bisa ditelusuri siapa yang mengubah apa.
 */
class ShiftManagementController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->input('tenant_id');
        $status   = $request->input('status');
        $dari     = $request->input('from');
        $sampai   = $request->input('to');
        $selisih  = $request->input('selisih');   // 'ada' = hanya yang tidak pas

        $q = Shift::query()
            ->withoutGlobalScopes()          // Superadmin melihat lintas tenant
            ->with(['user', 'tenant']);

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }
        if ($status) {
            $q->where('status', $status);
        }
        if ($dari) {
            $q->whereDate('start_time', '>=', $dari);
        }
        if ($sampai) {
            $q->whereDate('start_time', '<=', $sampai);
        }
        if ($selisih === 'ada') {
            $q->where(function ($w) {
                $w->where('difference', '!=', 0)->orWhereNull('difference');
            })->where('status', 'closed');
        }

        $rows = $q->orderByDesc('start_time')->paginate(20)->withQueryString();

        return view('backend.superadmin.shifts.index', [
            'rows'     => $rows,
            'tenants'  => Tenant::orderBy('name')->get(),
            'tenantId' => $tenantId,
            'status'   => $status,
            'from'     => $dari,
            'to'       => $sampai,
            'selisih'  => $selisih,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $shift = Shift::withoutGlobalScopes()->with('tenant')->findOrFail($id);

        // Pemisah ribuan dibersihkan lebih dulu: "1.062.000" harus terbaca
        // 1062000, bukan 1,062 (titik dianggap desimal).
        foreach (['starting_cash', 'actual_cash'] as $kolom) {
            if ($request->filled($kolom)) {
                $request->merge([$kolom => preg_replace('/[^\d]/', '', (string) $request->input($kolom))]);
            }
        }

        $data = $request->validate([
            'starting_cash' => ['required', 'numeric', 'min:0'],
            'actual_cash'   => ['nullable', 'numeric', 'min:0'],
            'alasan'        => ['required', 'string', 'max:255'],
        ], [
            'alasan.required' => 'Alasan koreksi wajib diisi — ini catatan yang tersimpan di log.',
        ]);

        $sebelum = [
            'modal'    => (float) $shift->starting_cash,
            'aktual'   => $shift->actual_cash === null ? null : (float) $shift->actual_cash,
            'expected' => $shift->expected_cash === null ? null : (float) $shift->expected_cash,
            'selisih'  => $shift->difference === null ? null : (float) $shift->difference,
        ];

        DB::transaction(function () use ($shift, $data) {
            $shift->starting_cash = round((float) $data['starting_cash'], 2);

            // Shift yang MASIH BERJALAN belum punya angka penutup; yang dikoreksi
            // hanya modalnya, sisanya diisi nanti saat ditutup seperti biasa.
            if ($shift->status === 'closed') {
                $penjualanTunai = (float) Order::withoutGlobalScopes()
                    ->where('payment_method', 'cash')
                    ->where('payment_status', 'paid')
                    ->where('shift_id', $shift->id)
                    ->whereNull('voided_at')
                    ->sum('grand_total');

                $pengeluaran = (float) Expense::withoutGlobalScopes()
                    ->where('shift_id', $shift->id)->sum('amount');

                $aktual = array_key_exists('actual_cash', $data) && $data['actual_cash'] !== null
                    ? round((float) $data['actual_cash'], 2)
                    : ($shift->actual_cash === null ? null : (float) $shift->actual_cash);

                $seharusnya = round($shift->starting_cash + $penjualanTunai - $pengeluaran, 2);

                $shift->cash_sales    = $penjualanTunai;
                $shift->expense_total = $pengeluaran;
                $shift->expected_cash = $seharusnya;
                $shift->actual_cash   = $aktual;
                $shift->difference    = $aktual === null ? null : round($aktual - $seharusnya, 2);
            }

            $shift->save();
        });

        $shift->refresh();
        $sesudah = [
            'modal'    => (float) $shift->starting_cash,
            'aktual'   => $shift->actual_cash === null ? null : (float) $shift->actual_cash,
            'expected' => $shift->expected_cash === null ? null : (float) $shift->expected_cash,
            'selisih'  => $shift->difference === null ? null : (float) $shift->difference,
        ];

        activity('shift-koreksi')
            ->performedOn($shift)
            ->causedBy(auth()->user())
            ->withProperties([
                'tenant'  => $shift->tenant?->name,
                'shift'   => $shift->id,
                'tanggal' => $shift->start_time ? \Carbon\Carbon::parse($shift->start_time)->format('d/m/Y H:i') : null,
                'sebelum' => $sebelum,
                'sesudah' => $sesudah,
                'alasan'  => $data['alasan'],
            ])
            ->log('Koreksi modal/aktual shift');

        return back()->with('success', sprintf(
            'Shift #%d (%s) dikoreksi: modal %s → %s%s.',
            $shift->id,
            $shift->tenant?->name ?? '-',
            'Rp ' . number_format($sebelum['modal'], 0, ',', '.'),
            'Rp ' . number_format($sesudah['modal'], 0, ',', '.'),
            $sebelum['aktual'] !== $sesudah['aktual']
                ? ', aktual Rp ' . number_format((float) $sebelum['aktual'], 0, ',', '.')
                  . ' → Rp ' . number_format((float) $sesudah['aktual'], 0, ',', '.')
                : ''
        ));
    }
}
