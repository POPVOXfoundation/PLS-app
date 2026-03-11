<?php

namespace App\Domain\Reviews;

use App\Domain\Analysis\EvidenceItem;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Institutions\Committee;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\LegislationObjective;
use App\Domain\Legislation\PlsReviewLegislation;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlsReview extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Reviews\PlsReviewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'committee_id',
        'legislature_id',
        'jurisdiction_id',
        'country_id',
        'title',
        'slug',
        'description',
        'status',
        'current_step_number',
        'start_date',
        'completed_at',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function legislature(): BelongsTo
    {
        return $this->belongsTo(Legislature::class);
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PlsReviewStep::class)->orderBy('step_number');
    }

    public function reviewLegislation(): HasMany
    {
        return $this->hasMany(PlsReviewLegislation::class);
    }

    public function legislation(): BelongsToMany
    {
        return $this->belongsToMany(Legislation::class, 'pls_review_legislation')
            ->withPivot('relationship_type')
            ->withTimestamps();
    }

    public function legislationObjectives(): HasMany
    {
        return $this->hasMany(LegislationObjective::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(EvidenceItem::class);
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(Stakeholder::class);
    }

    public function implementingAgencies(): HasMany
    {
        return $this->hasMany(ImplementingAgency::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function governmentResponses(): HasMany
    {
        return $this->hasMany(GovernmentResponse::class);
    }

    public function currentStep(): ?PlsReviewStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->firstWhere('step_number', $this->current_step_number);
        }

        return $this->steps()
            ->where('step_number', $this->current_step_number)
            ->first();
    }

    public function step(int $stepNumber): ?PlsReviewStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->firstWhere('step_number', $stepNumber);
        }

        return $this->steps()
            ->where('step_number', $stepNumber)
            ->first();
    }

    public function firstOpenStep(): ?PlsReviewStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->first(fn (PlsReviewStep $step): bool => ! $step->isTerminal());
        }

        return $this->steps()
            ->whereNotIn('status', [PlsStepStatus::Completed->value, PlsStepStatus::Skipped->value])
            ->orderBy('step_number')
            ->first();
    }

    public function firstOpenStepAfter(int $stepNumber): ?PlsReviewStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->first(
                fn (PlsReviewStep $step): bool => $step->step_number > $stepNumber && ! $step->isTerminal(),
            );
        }

        return $this->steps()
            ->where('step_number', '>', $stepNumber)
            ->whereNotIn('status', [PlsStepStatus::Completed->value, PlsStepStatus::Skipped->value])
            ->orderBy('step_number')
            ->first();
    }

    public function currentStepTitle(): string
    {
        return $this->currentStep()?->title ?? __('Step :number', ['number' => $this->current_step_number]);
    }

    public function progressPercentage(): int
    {
        $totalSteps = $this->relationLoaded('steps')
            ? max($this->steps->count(), 1)
            : max($this->steps()->count(), 1);

        return (int) round(($this->current_step_number / $totalSteps) * 100);
    }

    public function statusLabel(): string
    {
        $value = $this->status instanceof PlsReviewStatus
            ? $this->status->value
            : (string) $this->status;

        return Str::headline($value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlsReviewStatus::class,
            'start_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
