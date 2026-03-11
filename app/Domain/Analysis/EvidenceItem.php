<?php

namespace App\Domain\Analysis;

use App\Domain\Analysis\Enums\EvidenceType;
use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class EvidenceItem extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Analysis\EvidenceItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'document_id',
        'title',
        'evidence_type',
        'description',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

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
            'evidence_type' => EvidenceType::class,
        ];
    }
}
