<?php

namespace App\Domain\Legislation;

use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LegislationObjective extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Legislation\LegislationObjectiveFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legislation_id',
        'pls_review_id',
        'title',
        'description',
    ];

    public function legislation(): BelongsTo
    {
        return $this->belongsTo(Legislation::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }
}
