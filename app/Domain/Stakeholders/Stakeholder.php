<?php

namespace App\Domain\Stakeholders;

use App\Domain\Consultations\Submission;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Enums\StakeholderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Stakeholder extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Stakeholders\StakeholderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'name',
        'stakeholder_type',
        'contact_details',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stakeholder_type' => StakeholderType::class,
            'contact_details' => 'array',
        ];
    }
}
