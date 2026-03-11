<?php

namespace App\Domain\Institutions;

use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Legislature extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Institutions\LegislatureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'jurisdiction_id',
        'name',
        'slug',
        'legislature_type',
        'description',
    ];

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlsReview::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legislature_type' => LegislatureType::class,
        ];
    }
}
