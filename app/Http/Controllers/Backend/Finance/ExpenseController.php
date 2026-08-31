<?php

namespace App\Http\Controllers\Backend\Finance;

use App\Http\Controllers\Controller;
use App\Models\DailySalesTarget;
use App\Models\Expense;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class ExpenseController extends Controller
{
    /** Halaman pencatatan pengeluaran + ringkasan total pengeluaran hari ini. */
    public function index()
    {
        // "Hari ini" = tanggal operasional: bila ada shift terbuka yang melewati tengah malam,
        // pakai TANGGAL shift itu (biar konsisten dgn sidebar & tidak reset saat ganti hari).
        $openShift = $this->currentOpenShift();
        $scopeDate = $openShift
            ? Carbon::parse($openShift->start_time)->toDateString()
            : Carbon::today()->toDateString();

        // Basis kolom `date` (tanggal yang DIISI user) -> total mengikuti tanggal pengeluaran
        // yang dimaksud, bukan waktu pencatatan (mis. backdate / dicatat lewat tengah malam).
        $spent = (float) Expense::whereDate('date', $scopeDate)->sum('amount');

        return view('backend.finance.expenses.index', compact('spent'));
    }

    /** Shift kasir yang sedang terbuka (operator: miliknya; peninjau: shift toko yang berjalan). */
    private function currentOpenShift(): ?Shift
    {
        $isOperator = Auth::user()->can('shift.operate');
        return $isOperator
            ? Shift::where('user_id', Auth::id())->where('status', 'open')->latest('start_time')->first()
            : Shift::where('status', 'open')->latest('start_time')->first();
    }

    /** Sumber DataTables server-side (ter-scope otomatis per tenant). */
    public function getDataExpenses(Request $request)
    {
        try {
            $data = Expense::with('user')
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->select('expenses.*');

            return $this->buildExpenseDataTable($data);
        } catch (\Throwable $e) {
            // Jangan biarkan 500 -> tabel diam-diam kosong. Log penyebab & balas JSON valid.
            \Illuminate\Support\Facades\Log::error('getDataExpenses gagal: ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw'            => (int) $request->input('draw', 0),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Gagal memuat data pengeluaran: ' . $e->getMessage(),
            ]);
        }
    }

    /** Bangun response DataTables dari query yang sudah disiapkan. */
    protected function buildExpenseDataTable($data)
    {
        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('date', fn ($row) => '<span class="badge badge-light-primary fs-7">' . Carbon::parse($row->date)->translatedFormat('d M Y') . '</span>')
            // Badge "tidak dari laci" WAJIB terlihat di daftar: sebelumnya status ini hanya
            // muncul di dalam modal Edit satu per satu, sehingga pengeluaran yang tidak
            // membebani laci hilang tanpa jejak dan baru ketahuan saat tutup shift minus.
            ->addColumn('title', fn ($row) => '<span class="fw-bold text-gray-800">' . e($row->category) . '</span>'
                . (is_null($row->shift_id) ? ' <span class="badge badge-light-warning fs-8" title="Tidak mengurangi kas shift mana pun">tidak dari laci</span>' : '')
                . ($row->notes ? '<br><span class="text-muted fs-7">' . e(Str::limit($row->notes, 50)) . '</span>' : ''))
            ->addColumn('amount', fn ($row) => '<span class="fw-bold text-danger">Rp ' . number_format($row->amount, 0, ',', '.') . '</span>')
            ->addColumn('user', fn ($row) => e(optional($row->user)->name ?? 'Sistem'))
            ->addColumn('action', function ($row) {
                $d = htmlspecialchars(json_encode([
                    'id'       => $row->id,
                    'date'     => Carbon::parse($row->date)->format('Y-m-d'),
                    'category' => $row->category,
                    'amount'   => (int) $row->amount,
                    'notes'    => $row->notes,
                    'not_from_shift' => is_null($row->shift_id) ? 1 : 0,
                ]), ENT_QUOTES, 'UTF-8');

                return '<div class="d-flex justify-content-end gap-2">'
                    . '<button class="btn btn-sm btn-icon btn-light-primary btn-edit-expense" data-row="' . $d . '"><i class="ki-outline ki-pencil fs-4"></i></button>'
                    . '<button class="btn btn-sm btn-icon btn-light-danger btn-del-expense" data-id="' . $row->id . '"><i class="ki-outline ki-trash fs-4"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['date', 'title', 'amount', 'action'])
            ->make(true);
    }

    /** Catat pengeluaran baru. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'category' => 'required|string|max:255',
            'amount'   => 'required|numeric|min:0',
            'notes'    => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();
            $expense = Expense::create([
                'date'     => $data['date'],
                'category' => $data['category'],
                'amount'   => $data['amount'],
                'notes'    => $data['notes'] ?? null,
                'user_id'  => Auth::id(),
                // Set tenant eksplisit: cegah expense "yatim" (tenant_id NULL) bila direkam
                // dalam konteks tanpa tenant aktif (mis. Superadmin yg tetap punya tenant_id).
                // Jatuh ke tenant_id user pencatat agar tetap tampil di daftar tenant.
                'tenant_id' => app(\App\Tenancy\TenantManager::class)->id() ?? Auth::user()?->tenant_id,
            ]);
            // Toggle "bukan dari laci shift ini": keluarkan dari laci (shift_id NULL) -> tak
            // mengurangi selisih kas shift, tetap masuk laporan pengeluaran by kolom date.
            if ($request->boolean('not_from_shift')) {
                $expense->update(['shift_id' => null]);
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Pengeluaran berhasil dicatat!']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    /** Ubah pengeluaran (ter-scope per tenant via findOrFail). */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'category' => 'required|string|max:255',
            'amount'   => 'required|numeric|min:0',
            'notes'    => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();
            $expense = Expense::findOrFail($id);
            $expense->update([
                'date'     => $data['date'],
                'category' => $data['category'],
                'amount'   => $data['amount'],
                'notes'    => $data['notes'] ?? null,
            ]);
            // Toggle laci: centang -> keluarkan dari laci (shift_id NULL). Lepas centang ->
            // kembalikan ke laci shift yang terbuka saat dicatat (bila sebelumnya dikosongkan).
            if ($request->boolean('not_from_shift')) {
                $expense->update(['shift_id' => null]);
            } elseif (is_null($expense->shift_id)) {
                $expense->update(['shift_id' => $expense->resolveShift()?->id]);
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Pengeluaran berhasil diubah!']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengubah: ' . $e->getMessage()], 500);
        }
    }

    /** Hapus pengeluaran. */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            Expense::findOrFail($id)->delete();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Pengeluaran berhasil dihapus.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus.'], 500);
        }
    }
}
