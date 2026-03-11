<?php

namespace App\Domain\Analysis;

use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Analysis\RecommendationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'finding_id',
        'title',
        'description',
        'recommendation_type',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recommendation_type' => RecommendationType::class,
        ];
    }
}
