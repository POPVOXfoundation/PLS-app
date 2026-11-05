<?php

namespace App\Domain\Reviews;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReviewActivity extends Model
{
    protected $guarded = ['id'];

    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            'PlsReviewMembership' => __('Team member'), 'PlsReviewInvitation' => __('Invitation'),
            default => Str::headline($this->subject_type),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
