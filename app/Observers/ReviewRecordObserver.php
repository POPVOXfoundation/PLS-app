<?php

namespace App\Observers;

use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReviewMembership;
use App\Domain\Reviews\ReviewActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReviewRecordObserver
{
    public function creating(Model $model): void
    {
        if ($model instanceof Document && auth()->id()) {
            $model->metadata = array_merge($model->metadata ?? [], ['added_by' => auth()->id()]);
        }
    }

    public function created(Model $model): void
    {
        $this->record($model, 'added');
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Document) {
            $old = data_get($model->getRawOriginal('metadata') ? json_decode($model->getRawOriginal('metadata'), true) : [], 'extraction.status');
            $new = data_get($model->metadata, 'extraction.status');
            if ($old !== $new && in_array($new, ['completed', 'failed'])) {
                $this->record($model, $new === 'completed' ? 'text processing completed' : 'processing needs attention');

                return;
            }
            if (! auth()->id()) {
                return;
            }
        }
        if ($model->wasChanged(array_diff(array_keys($model->getChanges()), ['updated_at']))) {
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
        ReviewActivity::create([
            'pls_review_id' => $model->pls_review_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => class_basename($model),
            'subject_id' => $model->id,
            'title' => Str::limit((string) ($model instanceof PlsReviewMembership ? ($model->user?->name ?? 'Team member') : ($model->title ?? $model->name ?? $model->email ?? class_basename($model))), 250, ''),
        ]);
    }
}
