<?php

namespace App\Notifications;

use App\Domain\Reviews\PlsReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewAccessGrantedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PlsReview $review) {}

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
