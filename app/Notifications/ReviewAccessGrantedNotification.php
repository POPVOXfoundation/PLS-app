<?php

namespace App\Notifications;

use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class ReviewAccessGrantedNotification extends ReviewMailNotification
{
    public function __construct(public PlsReview $review, public ?int $membershipId = null)
    {
        $this->deliveryToken = (string) PlsReviewMembership::query()->whereKey($membershipId)->value('mail_token');
        $this->afterCommit();
    }

    public function deliveryRecord(): ?Model
    {
        return PlsReviewMembership::query()->whereKey($this->membershipId)->where('mail_token', $this->deliveryToken)->first();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You have access to a PLS review'))
            ->line(__('You have been added as a collaborator on ":review".', ['review' => $this->review->title]))
            ->action(__('Open review'), route('pls.reviews.workflow', $this->review));
    }
}
