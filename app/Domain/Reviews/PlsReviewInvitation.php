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
        'accepted_at',
        'expires_at',
        'mail_status', 'mail_sent_at', 'mail_queued_at',
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
        return $this->accepted_at === null && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function acceptFor(User $user): PlsReviewMembership
    {
        return DB::transaction(function () use ($user) {
            $invitation = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            abort_unless(mb_strtolower($invitation->email) === mb_strtolower($user->email), 403);
            if ($invitation->accepted_at) {
                $existing = $invitation->review->memberships()->where('user_id', $user->id)->first();
                abort_unless($existing, 410, 'This invitation has already been used. Request a new invitation.');

                return $existing;
            }
            abort_if($invitation->expires_at?->isPast(), 410, 'This invitation has expired. Ask the review owner to resend it.');
            $membership = $invitation->review->memberships()->firstOrCreate(['user_id' => $user->id], [
                'role' => $invitation->role, 'invited_by' => $invitation->invited_by,
            ]);
            if (! $invitation->accepted_at) {
                $invitation->update(['accepted_at' => now()]);
            }

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
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
            'mail_sent_at' => 'datetime', 'mail_queued_at' => 'datetime',
        ];
    }
}
