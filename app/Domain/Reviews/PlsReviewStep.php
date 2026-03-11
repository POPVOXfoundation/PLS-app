<?php

namespace App\Domain\Reviews;

use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlsReviewStep extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Reviews\PlsReviewStepFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'step_number',
        'step_key',
        'status',
        'started_at',
        'completed_at',
        'notes',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function getTitleAttribute(): string
    {
        return PlsReviewWorkflow::titleFor($this->step_key);
    }

    public function statusLabel(): string
    {
        $value = $this->status instanceof PlsStepStatus
            ? $this->status->value
            : (string) $this->status;

        return Str::headline($value);
    }

    public function isPending(): bool
    {
        return $this->status === PlsStepStatus::Pending;
    }

    public function isInProgress(): bool
    {
        return $this->status === PlsStepStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === PlsStepStatus::Completed;
    }

    public function isSkipped(): bool
    {
        return $this->status === PlsStepStatus::Skipped;
    }

    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isSkipped();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlsStepStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
