<?php

namespace App\Domain\Documents;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Documents\DocumentChunkFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'token_count',
        'embedding',
        'metadata',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }
}
