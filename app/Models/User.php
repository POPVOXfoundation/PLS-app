<?php

namespace App\Models;

use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use App\Models\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'password' => 'hashed',
        ];
    }

    public function canManagePlsReviews(): bool
    {
        return in_array($this->role, [
            UserRole::Admin,
            UserRole::ReviewGroupStaff,
            UserRole::Reviewer,
        ], true);
    }

    public function canViewPlsReviews(): bool
    {
        return in_array($this->role, UserRole::cases(), true);
    }

    public function ownedPlsReviews(): HasMany
    {
        return $this->hasMany(PlsReview::class, 'created_by');
    }

    public function plsReviewMemberships(): HasMany
    {
        return $this->hasMany(PlsReviewMembership::class);
    }

    public function accessiblePlsReviews(): BelongsToMany
    {
        return $this->belongsToMany(PlsReview::class, 'pls_review_memberships')
            ->withPivot(['role', 'invited_by'])
            ->withTimestamps();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
