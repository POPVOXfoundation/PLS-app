<?php

namespace App\Jobs;

use App\Domain\Reviews\PlsReviewInvitation;
use App\Notifications\ReviewInvitationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendReviewInvitation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $invitationId, public string $token) {}

    public function handle(): void
    {
        $invitation = PlsReviewInvitation::find($this->invitationId);
        if (! $invitation || $invitation->token !== $this->token || ! $invitation->isPending() || $invitation->mail_status === 'sent') {
            return;
        }
        if (in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['log', 'array'])) {
            $invitation->update(['mail_status' => 'not_configured']);

            return;
        }
        Notification::route('mail', $invitation->email)->notifyNow(new ReviewInvitationNotification($invitation));
        // Transport acceptance is not a delivery receipt.
        $invitation->update(['mail_status' => 'sent', 'mail_sent_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        PlsReviewInvitation::whereKey($this->invitationId)->where('token', $this->token)->whereNull('accepted_at')->update(['mail_status' => 'failed']);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
