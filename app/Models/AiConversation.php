<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu sesi percakapan AI milik seorang pengguna di satu tenant.
 * Isinya memuat angka penjualan, jadi ruang lingkup tenant wajib ditegakkan.
 */
class AiConversation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['uuid', 'tenant_id', 'user_id', 'kind', 'title', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (empty($m->uuid)) {
                $m->uuid = (string) Str::uuid();
            }
        });
    }

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }
}
