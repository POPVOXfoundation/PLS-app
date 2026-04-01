<?php

namespace App\Domain\Reviews;

use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PlsReviewInvitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'email',
        'role',
        'token',
        'invited_by',
        'accepted_at',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(64);
            }
        });
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function acceptFor(User $user): PlsReviewMembership
    {
        $this->update(['accepted_at' => now()]);

        return $this->review->memberships()->create([
            'user_id' => $user->id,
            'role' => $this->role,
            'invited_by' => $this->invited_by,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PlsReviewMembershipRole::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
