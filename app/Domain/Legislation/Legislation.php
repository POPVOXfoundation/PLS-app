<?php

namespace App\Domain\Legislation;

use App\Domain\Institutions\Jurisdiction;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Legislation extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Legislation\LegislationFactory> */
    use HasFactory;

    protected $table = 'legislation';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'jurisdiction_id',
        'title',
        'short_title',
        'legislation_type',
        'date_enacted',
        'summary',
    ];

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(LegislationObjective::class);
    }

    public function reviewLegislation(): HasMany
    {
        return $this->hasMany(PlsReviewLegislation::class);
    }

    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(PlsReview::class, 'pls_review_legislation')
            ->withPivot('relationship_type')
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legislation_type' => LegislationType::class,
            'date_enacted' => 'date',
        ];
    }
}
