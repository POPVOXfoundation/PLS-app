<?php

namespace App\Domain\Consultations;

use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Consultations\ConsultationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'title',
        'consultation_type',
        'held_at',
        'summary',
        'document_id',
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
            'consultation_type' => ConsultationType::class,
            'held_at' => 'datetime',
        ];
    }
}
