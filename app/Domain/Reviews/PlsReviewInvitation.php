<?php

namespace App\Domain\Reviews;

use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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
        'mail_status', 'mail_token', 'mail_requested_at', 'mail_accepted_at',
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
        return $this->accepted_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function acceptFor(User $user): PlsReviewMembership
    {
        return DB::transaction(function () use ($user): PlsReviewMembership {
            $invitation = static::query()->lockForUpdate()->findOrFail($this->id);
            abort_unless(hash_equals((string) $invitation->token, (string) $this->token) && $invitation->isPending(), 410);
            abort_unless(mb_strtolower($user->email) === mb_strtolower($invitation->email), 403);
            $review = $invitation->review()->lockForUpdate()->firstOrFail();
            $membership = $review->memberships()->firstOrCreate(['user_id' => $user->id], [
                'role' => $invitation->role,
                'invited_by' => $invitation->invited_by,
            ]);
            $invitation->update(['accepted_at' => now()]);

            return $membership;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PlsReviewMembershipRole::class,
            'mail_requested_at' => 'datetime',
            'mail_accepted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
