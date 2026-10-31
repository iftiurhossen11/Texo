<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'retrieved_chunk_ids',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'retrieved_chunk_ids' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
