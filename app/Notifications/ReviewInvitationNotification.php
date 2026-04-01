<?php

namespace App\Notifications;

use App\Domain\Reviews\PlsReviewInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PlsReviewInvitation $invitation,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $review = $this->invitation->review;
        $inviter = $this->invitation->invitedBy;

        return (new MailMessage)
            ->subject(__('You\'ve been invited to a PLS review'))
            ->greeting(__('Hello!'))
            ->line(__(':inviter has invited you to collaborate on the review ":review" as a :role.', [
                'inviter' => $inviter->name,
                'review' => $review->title,
                'role' => $this->invitation->role->label(),
            ]))
            ->action(__('Accept invitation'), route('pls.invitations.accept', ['token' => $this->invitation->token]))
            ->line(__('This invitation was sent to :email.', ['email' => $this->invitation->email]));
    }
}
