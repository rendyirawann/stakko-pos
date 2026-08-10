<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Tenancy\Addon;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Berikan modul tambahan (add-on) ke sebuah tenant.
 *
 * Add-on dijual per bulan dan biasanya disamakan masa berlakunya dengan
 * langganan yang sedang berjalan, supaya perpanjangannya sekali jalan. Karena
 * itu periode diambil dari langganan aktif tenant kecuali ditentukan sendiri.
 *
 * Contoh:
 *   php artisan tenant:addon 2 inventory_hpp --label="HPP & Inventory" \
 *       --harga=10000 --bulan=6 --peran=owner
 */
class BeriAddonTenant extends Command
{
    protected $signature = 'tenant:addon
        {tenant : ID atau nama tenant}
        {module : kunci modul, mis. inventory_hpp}
        {--label= : nama yang dibaca orang}
        {--harga=10000 : harga per bulan}
        {--bulan= : jumlah bulan; kosong = ikut sisa langganan aktif}
        {--mulai= : tanggal mulai (Y-m-d); kosong = ikut langganan aktif}
        {--peran=* : peran yang boleh membuka layarnya; kosong = semua peran}
        {--catatan= : catatan yang tampil di riwayat}';

    protected $description = 'Berikan modul tambahan (add-on) ke tenant di luar paketnya';

    public function handle(): int
    {
        $kunci  = $this->argument('tenant');
        $tenant = is_numeric($kunci)
            ? Tenant::find((int) $kunci)
            : Tenant::where('name', 'ilike', '%' . $kunci . '%')->first();

        if (! $tenant) {
            $this->error("Tenant '{$kunci}' tidak ditemukan.");
            return self::FAILURE;
        }

        $module = $this->argument('module');
        $harga  = (float) $this->option('harga');

        // Periode: ikut langganan aktif bila tidak ditentukan, supaya add-on dan
        // paketnya habis bersamaan dan tidak ada fitur yang hidup sendirian.
        $langganan = $tenant->subscriptions()
            ->where('status', 'paid')->orderByDesc('ends_at')->first();

        $mulai = $this->option('mulai')
            ? Carbon::parse($this->option('mulai'))
            : ($langganan?->starts_at ? Carbon::parse($langganan->starts_at) : Carbon::today());

        $bulan = (int) ($this->option('bulan')
            ?: ($langganan?->ends_at ? max(1, $mulai->diffInMonths(Carbon::parse($langganan->ends_at))) : 1));

        $selesai = $mulai->copy()->addMonths($bulan);
        $peran   = array_values(array_filter((array) $this->option('peran')));

        $addon = TenantAddon::create([
            'tenant_id'       => $tenant->id,
            'module'          => $module,
            'label'           => $this->option('label') ?: $module,
            'price_per_month' => $harga,
            'months'          => $bulan,
            'amount'          => round($harga * $bulan, 2),
            'status'          => 'active',
            'starts_at'       => $mulai->toDateString(),
            'ends_at'         => $selesai->toDateString(),
            'paid_at'         => now(),
            'allowed_roles'   => $peran ?: null,
            'note'            => $this->option('catatan'),
        ]);

        Addon::lupakan();

        $this->info(sprintf(
            "Add-on '%s' aktif untuk %s: Rp %s/bulan x %d bulan = Rp %s (%s s/d %s), dibuka oleh: %s.",
            $addon->label,
            $tenant->name,
            number_format($harga, 0, ',', '.'),
            $bulan,
            number_format($addon->amount, 0, ',', '.'),
            $mulai->format('d M Y'),
            $selesai->format('d M Y'),
            $addon->labelPeran()
        ));

        return self::SUCCESS;
    }
}
