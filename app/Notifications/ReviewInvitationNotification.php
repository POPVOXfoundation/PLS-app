<?php

namespace App\Notifications;

use App\Domain\Reviews\PlsReviewInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class ReviewInvitationNotification extends ReviewMailNotification
{
    public string $invitationToken = '';

    public function __construct(public PlsReviewInvitation $invitation)
    {
        $this->deliveryToken = (string) $invitation->mail_token;
        $this->invitationToken = $invitation->token;
        $this->afterCommit();
    }

    public function deliveryRecord(): ?Model
    {
        $record = PlsReviewInvitation::query()->whereKey($this->invitation->id)->where('mail_token', $this->deliveryToken)->where('token', $this->invitationToken)->first();

        return $record?->isPending() ? $record : null;
    }

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
            ->action(__('Accept invitation'), route('pls.invitations.accept', ['token' => $this->invitationToken]))
            ->line(__('This invitation was sent to :email.', ['email' => $this->invitation->email]));
    }
}
