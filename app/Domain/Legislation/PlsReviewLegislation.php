<?php

namespace App\Domain\Legislation;

use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class PlsReviewLegislation extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Legislation\PlsReviewLegislationFactory> */
    use HasFactory;

    protected $table = 'pls_review_legislation';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'legislation_id',
        'relationship_type',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function legislation(): BelongsTo
    {
        return $this->belongsTo(Legislation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship_type' => ReviewLegislationRelationshipType::class,
        ];
    }
}
