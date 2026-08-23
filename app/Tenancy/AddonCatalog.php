<?php

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\TenantAddon;

/**
 * Pembaca katalog add-on (config/addons.php).
 *
 * Dipisah dari Addon (yang menjawab "boleh tidak?") karena ini menjawab
 * pertanyaan berbeda: "apa saja yang bisa dibeli, berapa harganya, dan mana
 * yang sudah dimiliki tenant ini".
 */
class AddonCatalog
{
    /** @return array<string, array> */
    public static function semua(): array
    {
        return (array) config('addons', []);
    }

    public static function ada(string $module): bool
    {
        return array_key_exists($module, self::semua());
    }

    public static function item(string $module): ?array
    {
        return self::semua()[$module] ?? null;
    }

    public static function label(string $module): string
    {
        return self::item($module)['label'] ?? $module;
    }

    public static function harga(string $module): int
    {
        return (int) (self::item($module)['harga'] ?? 0);
    }

    /**
     * Katalog untuk sebuah tenant, sudah ditandai status kepemilikannya.
     *
     * Modul yang sudah termasuk PAKET tenant tidak ditawarkan lagi — menawarkan
     * sesuatu yang sudah dimiliki hanya membingungkan dan berisiko dibayar dua kali.
     */
    public static function untukTenant(?Tenant $tenant): array
    {
        if (! $tenant) {
            return [];
        }

        $dariPaket = Plan::modules($tenant->plan, $tenant->vertical);
        $milik = TenantAddon::where('tenant_id', $tenant->id)->get()->keyBy('module');

        $hasil = [];
        foreach (self::semua() as $kunci => $item) {
            // Hormati pembatasan vertical: HPP tidak relevan untuk laundry.
            $vertical = $item['vertical'] ?? null;
            if ($vertical && $tenant->vertical && ! in_array($tenant->vertical, (array) $vertical, true)) {
                continue;
            }
            if (in_array($kunci, $dariPaket, true)) {
                continue;   // sudah dari paket
            }

            $punya = $milik->get($kunci);
            $hasil[$kunci] = $item + [
                'module' => $kunci,
                'status' => $punya?->status,
                'aktif'  => (bool) $punya?->aktif(),
                'berlaku_sampai' => $punya?->ends_at,
                'menunggu' => $punya && $punya->status === 'pending',
            ];
        }

        return $hasil;
    }
}
