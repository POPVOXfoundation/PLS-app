<?php

namespace App\Domain\Documents;

use App\Domain\Documents\Enums\AssistantSourceScope;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantSourceDocument extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Documents\AssistantSourceDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'scope',
        'country_id',
        'jurisdiction_id',
        'legislature_id',
        'pls_review_id',
        'storage_path',
        'mime_type',
        'file_size',
        'summary',
        'content',
        'metadata',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function legislature(): BelongsTo
    {
        return $this->belongsTo(Legislature::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function scopeForGlobalGrounding(Builder $query): Builder
    {
        return $query->where('scope', AssistantSourceScope::Global);
    }

    public function scopeForJurisdictionContext(Builder $query, PlsReview $review): Builder
    {
        return $query
            ->where('scope', AssistantSourceScope::Jurisdiction)
            ->where(function (Builder $query) use ($review): void {
                $matches = 0;

                if ($review->country_id !== null) {
                    $query->orWhere('country_id', $review->country_id);
                    $matches++;
                }

                if ($review->jurisdiction_id !== null) {
                    $query->orWhere('jurisdiction_id', $review->jurisdiction_id);
                    $matches++;
                }

                if ($review->legislature_id !== null) {
                    $query->orWhere('legislature_id', $review->legislature_id);
                    $matches++;
                }

                if ($matches === 0) {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => AssistantSourceScope::class,
            'metadata' => 'array',
        ];
    }
}
