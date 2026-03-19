<?php

namespace App\Domain\Reviews;

use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PlsReviewMembership extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Reviews\PlsReviewMembershipFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'user_id',
        'role',
        'invited_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $membership): void {
            $membership->ensureOnlyOneOwnerExists();
            $membership->ensureOwnerCannotBeDemoted();
        });

        static::saved(function (self $membership): void {
            $membership->syncReviewCreator();
        });

        static::deleting(function (self $membership): void {
            $membership->ensureOwnerCannotBeRemoved();
        });
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isOwner(): bool
    {
        return $this->role === PlsReviewMembershipRole::Owner;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PlsReviewMembershipRole::class,
        ];
    }

    private function ensureOnlyOneOwnerExists(): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $ownerExists = self::query()
            ->where('pls_review_id', $this->pls_review_id)
            ->where('role', PlsReviewMembershipRole::Owner->value)
            ->when(
                $this->exists,
                fn ($query) => $query->where($this->getQualifiedKeyName(), '!=', $this->getKey()),
            )
            ->exists();

        if ($ownerExists) {
            throw ValidationException::withMessages([
                'role' => 'A review may only have one owner.',
            ]);
        }
    }

    private function ensureOwnerCannotBeDemoted(): void
    {
        if (! $this->exists) {
            return;
        }

        $originalRole = $this->getRawOriginal('role');

        if ($originalRole !== PlsReviewMembershipRole::Owner->value || $this->isOwner()) {
            return;
        }

        throw ValidationException::withMessages([
            'role' => 'Review ownership cannot be reassigned from this screen.',
        ]);
    }

    private function ensureOwnerCannotBeRemoved(): void
    {
        $persistedRole = $this->exists
            ? $this->getRawOriginal('role')
            : ($this->role instanceof PlsReviewMembershipRole ? $this->role->value : $this->role);

        if ($persistedRole !== PlsReviewMembershipRole::Owner->value) {
            return;
        }

        throw ValidationException::withMessages([
            'membership' => 'Review ownership cannot be removed.',
        ]);
    }

    private function syncReviewCreator(): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $review = $this->review()->first();

        if ($review === null || $review->created_by === $this->user_id) {
            return;
        }

        $review->forceFill([
            'created_by' => $this->user_id,
        ])->saveQuietly();
    }
}
