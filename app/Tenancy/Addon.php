<?php

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * MODUL TAMBAHAN (ADD-ON) DI LUAR PAKET.
 *
 * Ada dua pertanyaan berbeda yang sering tertukar, dan di sini sengaja dipisah:
 *
 *   1. aktif()      — "fitur ini hidup untuk toko itu?"  → menentukan perilaku
 *                     sistem (stok terpotong, HPP dihitung), berlaku untuk semua
 *                     pengguna toko tersebut.
 *   2. bolehLihat() — "orang ini boleh membuka layarnya?" → menentukan menu &
 *                     akses halaman, bisa dibatasi ke peran tertentu.
 *
 * Menggabungkan keduanya akan membuat stok berhenti terpotong hanya karena yang
 * menjaga kasir bukan pemilik toko.
 *
 * Hasil pembacaan di-cache per permintaan supaya satu halaman yang memeriksa
 * modul berkali-kali (menu, sidebar, route) tidak memukul database berulang.
 */
class Addon
{
    /** @var array<string, ?TenantAddon> */
    private static array $ingatan = [];

    /** Add-on aktif milik tenant untuk sebuah modul, atau null. */
    public static function milik(?Tenant $tenant, string $module): ?TenantAddon
    {
        if (! $tenant) {
            return null;
        }

        $kunci = $tenant->id . '|' . $module;
        if (array_key_exists($kunci, self::$ingatan)) {
            return self::$ingatan[$kunci];
        }

        $addon = TenantAddon::where('tenant_id', $tenant->id)
            ->where('module', $module)
            ->where('status', 'active')
            ->orderByDesc('ends_at')
            ->get()
            ->first(fn (TenantAddon $a) => $a->aktif());

        return self::$ingatan[$kunci] = $addon;
    }

    /** Fitur hidup untuk toko ini (tanpa melihat peran pengguna). */
    public static function aktif(?Tenant $tenant, string $module): bool
    {
        return self::milik($tenant, $module) !== null;
    }

    /**
     * Boleh membuka layar modul ini?
     *
     * Bila modulnya sudah termasuk paket, aturan peran add-on tidak berlaku —
     * pembatasan itu bagian dari kesepakatan add-on, bukan aturan umum aplikasi.
     */
    public static function bolehLihat(?Tenant $tenant, string $module, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($user && $user->isSuperadmin()) {
            return true;
        }
        if (! $tenant) {
            return true;
        }

        // Dari paket / trial -> ikut aturan izin biasa, tanpa batasan peran tambahan.
        if ($tenant->isOnTrial() && $tenant->hasActiveAccess()) {
            return true;
        }
        if (in_array($module, Plan::modules($tenant->plan, $tenant->vertical), true)) {
            return true;
        }

        $addon = self::milik($tenant, $module);

        return $addon !== null && $addon->bolehDibuka($user);
    }

    /** Bersihkan ingatan (dipakai setelah add-on diubah, dan oleh pengujian). */
    public static function lupakan(): void
    {
        self::$ingatan = [];
    }
}
