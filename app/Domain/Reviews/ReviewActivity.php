<?php

namespace App\Domain\Reviews;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewActivity extends Model
{
    protected $fillable = ['pls_review_id', 'user_id', 'action', 'subject_type', 'subject_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectUrl(): ?string
    {
        if ($this->action === 'removed') {
            return null;
        }

        return match ($this->subject_type) {
            'Document' => route('pls.reviews.documents.show', [$this->pls_review_id, $this->subject_id]),
            'Report' => route('pls.reviews.reports.edit', [$this->pls_review_id, $this->subject_id]),
            default => null,
        };
    }
}
