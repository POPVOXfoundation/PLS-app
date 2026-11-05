<?php

namespace App\Domain\Analysis;

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Reviews\PlsReview;
use Database\Factories\Domain\Analysis\FindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'title',
        'finding_type',
        'summary',
        'detail',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function citations(): HasMany
    {
        return $this->hasMany(FindingCitation::class);
    }

    public function supportFingerprint(): string
    {
        return hash('sha256', json_encode($this->only(['title', 'finding_type', 'summary', 'detail']), JSON_THROW_ON_ERROR));
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finding_type' => FindingType::class,
        ];
    }
}
