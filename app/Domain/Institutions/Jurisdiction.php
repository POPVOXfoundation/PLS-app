<?php

namespace App\Domain\Institutions;

use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jurisdiction extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Institutions\JurisdictionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'country_id',
        'name',
        'slug',
        'jurisdiction_type',
        'parent_id',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function legislatures(): HasMany
    {
        return $this->hasMany(Legislature::class);
    }

    public function legislation(): HasMany
    {
        return $this->hasMany(Legislation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlsReview::class);
    }

    public function reviewGroups(): HasMany
    {
        return $this->hasMany(ReviewGroup::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jurisdiction_type' => JurisdictionType::class,
        ];
    }
}
