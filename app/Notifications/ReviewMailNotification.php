<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Throwable;

abstract class ReviewMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public string $deliveryToken = '';

    public bool $deleteWhenMissingModels = true;

    abstract public function deliveryRecord(): ?Model;

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $record = $this->deliveryRecord();
        if ($record === null || $record->mail_status === 'accepted_by_mail_service') {
            return false;
        }
        if (in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['log', 'array'], true)) {
            $this->updateDelivery(['mail_status' => 'not_configured']);

            return false;
        }

        return true;
    }

    public function markAccepted(): void
    {
        $this->updateDelivery(['mail_status' => 'accepted_by_mail_service', 'mail_accepted_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->updateDelivery(['mail_status' => 'failed']);
    }

    /** @param array<string, mixed> $attributes */
    private function updateDelivery(array $attributes): void
    {
        $record = $this->deliveryRecord();
        if ($record !== null) {
            $record->newQuery()->whereKey($record->id)->where('mail_token', $this->deliveryToken)
                ->where('mail_status', '!=', 'accepted_by_mail_service')->update($attributes);
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
