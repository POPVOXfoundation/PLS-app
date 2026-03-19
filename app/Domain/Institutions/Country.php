<?php

namespace App\Domain\Institutions;

use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Institutions\CountryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'default_locale',
    ];

    public function jurisdictions(): HasMany
    {
        return $this->hasMany(Jurisdiction::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlsReview::class);
    }

    public function reviewGroups(): HasMany
    {
        return $this->hasMany(ReviewGroup::class);
    }
}
