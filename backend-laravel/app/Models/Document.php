<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'workspace_id',
        'original_filename',
        'file_type',
        'storage_path',
        'source_url',
        'source_type',
        'doc_type',
        'department',
        'status',
        'error_message',
        'uploaded_by',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'indexed_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
