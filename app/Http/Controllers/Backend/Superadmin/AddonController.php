<?php

namespace App\Http\Controllers\Backend\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Tenancy\Addon;
use App\Tenancy\AddonCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Kelola fitur tambahan (add-on) semua tenant — khusus Superadmin.
 *
 * Menutup lingkaran yang sebelumnya hanya bisa lewat artisan: tenant mengajukan
 * dari halaman Langganan (status `pending`), lalu diaktifkan di sini. Pengajuan
 * yang tidak pernah dilihat siapa pun sama saja dengan tidak ada.
 */
class AddonController extends Controller
{
    public function index(Request $request)
    {
        $q = TenantAddon::query()->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        if ($request->filled('cari')) {
            $cari = trim((string) $request->input('cari'));
            $idTenant = Tenant::where('name', 'ilike', '%' . $cari . '%')->pluck('id');
            $q->where(function ($w) use ($cari, $idTenant) {
                $w->whereIn('tenant_id', $idTenant)->orWhere('module', 'ilike', '%' . $cari . '%');
            });
        }

        $addons = $q->limit(200)->get();
        $tenants = Tenant::whereIn('id', $addons->pluck('tenant_id')->unique())->get()->keyBy('id');

        return view('backend.superadmin.addons.index', [
            'addons'  => $addons,
            'tenants' => $tenants,
            'katalog' => AddonCatalog::semua(),
            'cari'    => $request->input('cari'),
            'menunggu' => TenantAddon::where('status', 'pending')->count(),
        ]);
    }

    /** Aktifkan (atau perbarui) add-on, disamakan masa berlakunya dengan langganan. */
    public function aktifkan(Request $request, int $id)
    {
        $addon = TenantAddon::findOrFail($id);

        $request->validate([
            'harga'  => ['required', 'numeric', 'min:0'],
            'mulai'  => ['required', 'date'],
            'sampai' => ['required', 'date', 'after:mulai'],
            'peran'  => ['nullable', 'string', 'max:200'],
        ], [
            'sampai.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ]);

        $mulai = Carbon::parse($request->input('mulai'))->startOfDay();
        $sampai = Carbon::parse($request->input('sampai'))->startOfDay();

        // Menolak periode yang sudah lewat: add-on seperti itu tampak aktif di
        // daftar tetapi fiturnya tidak pernah menyala.
        if ($sampai->copy()->endOfDay()->isPast()) {
            return back()->with('error', 'Periode yang dipilih sudah lewat, add-on tidak akan aktif.');
        }

        $bulan = max(1, (int) round($mulai->diffInDays($sampai) / 30));
        $harga = (float) $request->input('harga');
        $peran = collect(explode(',', (string) $request->input('peran')))
            ->map(fn ($p) => trim($p))->filter()->values()->all();

        $addon->update([
            'price_per_month' => $harga,
            'months'   => $bulan,
            'amount'   => round($harga * $bulan, 2),
            'status'   => 'active',
            'starts_at' => $mulai->toDateString(),
            'ends_at'  => $sampai->toDateString(),
            'paid_at'  => $addon->paid_at ?: now(),
            'allowed_roles' => $peran ?: null,
        ]);

        Addon::lupakan();
        activity('addon-aktifkan')
            ->withProperties(['tenant_id' => $addon->tenant_id, 'module' => $addon->module,
                              'periode' => $mulai->toDateString() . ' s/d ' . $sampai->toDateString(),
                              'nominal' => $addon->amount])
            ->log("Add-on {$addon->module} diaktifkan");

        return back()->with('success', "Add-on {$addon->label} diaktifkan sampai {$sampai->translatedFormat('d M Y')}.");
    }

    public function batalkan(int $id)
    {
        $addon = TenantAddon::findOrFail($id);
        $addon->update(['status' => 'cancelled']);
        Addon::lupakan();

        activity('addon-batalkan')
            ->withProperties(['tenant_id' => $addon->tenant_id, 'module' => $addon->module])
            ->log("Add-on {$addon->module} dibatalkan");

        return back()->with('success', 'Add-on dibatalkan.');
    }

    /** Berikan add-on langsung tanpa menunggu pengajuan tenant. */
    public function beri(Request $request)
    {
        $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'module'    => ['required', 'string', 'max:60'],
        ]);

        $module = (string) $request->input('module');
        if (! AddonCatalog::ada($module)) {
            return back()->with('error', "Modul '{$module}' tidak ada di katalog.");
        }

        $tenant = Tenant::findOrFail($request->integer('tenant_id'));

        // Periode disamakan dengan langganan berbayar yang sedang berjalan agar
        // add-on dan paketnya habis bersamaan — tidak ada fitur yang hidup sendirian.
        $langganan = Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'paid')->orderByDesc('ends_at')->first();

        $mulai = Carbon::today();
        $sampai = $langganan?->ends_at
            ? Carbon::parse($langganan->ends_at)->startOfDay()
            : Carbon::today()->addMonth();

        if ($sampai->lte($mulai)) {
            return back()->with('error', 'Langganan tenant ini sudah berakhir. Perpanjang langganannya dulu, atau atur periode add-on secara manual.');
        }

        $item = AddonCatalog::item($module);
        $bulan = max(1, (int) round($mulai->diffInDays($sampai) / 30));
        $harga = (float) $item['harga'];

        $addon = TenantAddon::create([
            'tenant_id' => $tenant->id,
            'module'    => $module,
            'label'     => $item['label'],
            'price_per_month' => $harga,
            'months'    => $bulan,
            'amount'    => round($harga * $bulan, 2),
            'status'    => 'active',
            'starts_at' => $mulai->toDateString(),
            'ends_at'   => $sampai->toDateString(),
            'paid_at'   => now(),
            'allowed_roles' => $item['peran_default'] ?? null,
            'note'      => 'Diberikan Superadmin, disamakan dengan masa langganan.',
        ]);

        Addon::lupakan();
        activity('addon-beri')
            ->withProperties(['tenant_id' => $tenant->id, 'module' => $module, 'nominal' => $addon->amount])
            ->log("Add-on {$module} diberikan ke {$tenant->name}");

        return back()->with('success', "{$item['label']} aktif untuk {$tenant->name} sampai {$sampai->translatedFormat('d M Y')} (Rp " . number_format($addon->amount, 0, ',', '.') . ').');
    }
}
