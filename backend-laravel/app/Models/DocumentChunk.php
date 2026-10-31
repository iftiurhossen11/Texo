<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'section_heading',
        'page_number',
        'content',
        'token_count',
        'vector_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
