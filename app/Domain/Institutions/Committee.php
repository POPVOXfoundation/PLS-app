<?php

namespace App\Domain\Institutions;

use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Institutions\CommitteeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legislature_id',
        'name',
        'slug',
        'description',
    ];

    public function legislature(): BelongsTo
    {
        return $this->belongsTo(Legislature::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlsReview::class);
    }
}
