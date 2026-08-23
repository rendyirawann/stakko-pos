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
        {--sampai= : tanggal berakhir (Y-m-d); kosong = dihitung dari --bulan}
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

        // --sampai dipakai bila diberikan, supaya add-on bisa berhenti PERSIS di
        // tanggal langganan berakhir. Menghitung lewat jumlah bulan tidak selalu
        // bisa mendarat tepat di tanggal itu.
        if ($this->option('sampai')) {
            $selesai = Carbon::parse($this->option('sampai'))->startOfDay();
            if ($selesai->lte($mulai)) {
                $this->error('--sampai harus setelah tanggal mulai.');

                return self::FAILURE;
            }
            // Jumlah bulan ditaksir dari rentang nyata, dipakai untuk nominal.
            $bulan = max(1, (int) round($mulai->diffInDays($selesai) / 30));
        } else {
            $selesai = $mulai->copy()->addMonths($bulan);
        }
        $peran   = array_values(array_filter((array) $this->option('peran')));

        // Periode diambil dari langganan aktif tenant. Bila langganan itu sudah
        // lewat, add-on akan lahir dalam keadaan kedaluwarsa -- fiturnya tidak
        // pernah menyala, sementara perintah ini tampak berhasil. Itu jebakan
        // yang mahal untuk ditelusuri, jadi hentikan dan minta tanggal eksplisit.
        if ($selesai->copy()->endOfDay()->isPast()) {
            $this->error(sprintf(
                "Periode yang terhitung (%s s/d %s) SUDAH LEWAT, jadi add-on tidak akan aktif.",
                $mulai->format('d M Y'),
                $selesai->format('d M Y')
            ));
            $this->line('Penyebab tersering: langganan tenant ini sudah berakhir, dan periode add-on mengikutinya.');
            $this->line('Tentukan sendiri periodenya, contoh:');
            $this->line(sprintf(
                '  php artisan tenant:addon %s %s --mulai=%s --bulan=12',
                $this->argument('tenant'),
                $module,
                now()->toDateString()
            ));

            return self::FAILURE;
        }

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
