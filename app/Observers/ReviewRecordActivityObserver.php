<?php

namespace App\Observers;

use App\Domain\Reviews\ReviewActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReviewRecordActivityObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'added');
    }

    public function updated(Model $model): void
    {
        if ($model->wasChanged(array_diff(array_keys($model->getChanges()), ['updated_at', 'mail_status', 'mail_accepted_at', 'mail_requested_at', 'mail_token']))) {
            $this->record($model, 'updated');
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'removed');
    }

    private function record(Model $model, string $action): void
    {
        if (! $model->pls_review_id) {
            return;
        }
        ReviewActivity::query()->create([
            'pls_review_id' => $model->pls_review_id, 'user_id' => auth()->id(),
            'action' => $action, 'subject_type' => class_basename($model), 'subject_id' => $model->id,
            'title' => Str::limit((string) ($model->title ?? $model->name ?? $model->email ?? class_basename($model)), 250, ''),
        ]);
    }
}
