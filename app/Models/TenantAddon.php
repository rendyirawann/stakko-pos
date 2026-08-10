<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu modul tambahan yang dibeli tenant di luar paketnya.
 *
 * Aktif = status 'active' DAN masa berlakunya belum lewat. Pemeriksaan tanggal
 * dilakukan di sini, bukan hanya lewat status, supaya add-on yang kedaluwarsa
 * tetap tertutup meski belum sempat diperbarui statusnya oleh penjadwal.
 */
class TenantAddon extends Model
{
    protected $table = 'tenant_addons';

    protected $fillable = [
        'uuid', 'tenant_id', 'module', 'label', 'price_per_month', 'months', 'amount',
        'status', 'starts_at', 'ends_at', 'paid_at', 'allowed_roles', 'note', 'created_by',
    ];

    protected $casts = [
        'price_per_month' => 'decimal:2',
        'amount'          => 'decimal:2',
        'months'          => 'integer',
        'starts_at'       => 'date',
        'ends_at'         => 'date',
        'paid_at'         => 'datetime',
        'allowed_roles'   => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (empty($m->uuid)) {
                $m->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Masih berlaku? */
    public function aktif(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->endOfDay()->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Bolehkah peran ini membuka layarnya?
     *
     * Add-on bisa dibeli untuk seluruh toko tetapi layarnya dibatasi ke peran
     * tertentu — mis. stok tetap terpotong tiap penjualan oleh kasir, sementara
     * layar HPP hanya boleh dibuka pemilik.
     */
    public function bolehDibuka(?User $user): bool
    {
        $peran = $this->allowed_roles;
        if (empty($peran)) {
            return true;    // tidak dibatasi
        }
        if (! $user) {
            return false;
        }

        return $user->isSuperadmin() || $user->hasAnyRole($peran);
    }

    public function labelPeran(): string
    {
        return empty($this->allowed_roles) ? 'semua peran' : implode(', ', $this->allowed_roles);
    }
}
