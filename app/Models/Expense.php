<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\LogsAllActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Expense extends Model
{
    use BelongsToTenant, LogsAllActivity;

    protected $fillable = ['uuid', 'tenant_id', 'shift_id', 'date', 'category', 'notes', 'amount', 'user_id'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            // Tautkan ke shift yang sedang TERBUKA di tenant ini, SIAPA PUN pencatatnya.
            //
            // Aturan lama mencari shift milik PENCATAT (Auth::id()). Owner/admin memang
            // tidak pernah membuka shift sendiri, jadi hasilnya selalu NULL -> pengeluaran
            // mereka diam-diam tidak mengurangi laci saat tutup shift, dan kasir tampak
            // kurang setor sebesar nominal itu.
            //
            // tenant_id dikunci eksplisit: TenantScope TIDAK diterapkan bila tak ada tenant
            // aktif (Superadmin/proses sistem), sehingga tanpa kunci ini pengeluaran bisa
            // nyantol ke shift tenant lain.
            //
            // Bila uangnya memang bukan dari laci (mis. gaji via transfer), pakai toggle
            // "Bukan dari laci/kas shift" -> shift_id dikosongkan lagi.
            if (empty($model->shift_id) && ! empty($model->tenant_id)) {
                $model->shift_id = Shift::where('tenant_id', $model->tenant_id)
                    ->where('status', 'open')
                    ->latest('start_time')
                    ->value('id');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Shift/laci tempat pengeluaran ini dibebankan (null = tak mengurangi laci mana pun). */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Shift yang SEDANG TERBUKA saat pengeluaran ini dicatat (berdasarkan created_at) —
     * menunjukkan "laci/kas shift mana" uang ini keluar. Ter-scope per-tenant otomatis
     * (Shift memakai BelongsToTenant). Null bila dicatat di luar jam shift manapun.
     */
    public function resolveShift(): ?Shift
    {
        if (! $this->created_at) {
            return null;
        }

        return Shift::with('user')
            ->where('start_time', '<=', $this->created_at)
            ->where(function ($q) {
                $q->whereNull('end_time')->orWhere('end_time', '>=', $this->created_at);
            })
            ->orderByDesc('start_time')
            ->first();
    }
}
