<?php

namespace App\Domain\Stakeholders;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ImplementingAgency extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Stakeholders\ImplementingAgencyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'name',
        'agency_type',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agency_type' => ImplementingAgencyType::class,
        ];
    }
}
