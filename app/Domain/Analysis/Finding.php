<?php

namespace App\Domain\Analysis;

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Finding extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Analysis\FindingFactory> */
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
