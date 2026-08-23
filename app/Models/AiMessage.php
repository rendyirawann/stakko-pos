<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'tenant_id', 'role', 'content',
        'sources', 'brain', 'tokens_in', 'tokens_out', 'ms',
    ];

    protected $casts = ['sources' => 'array'];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
