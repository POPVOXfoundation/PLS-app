<?php

namespace App\Domain\Reviews;

use App\Domain\Analysis\EvidenceItem;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\LegislationObjective;
use App\Domain\Legislation\PlsReviewLegislation;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Stakeholder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;

class PlsReview extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Reviews\PlsReviewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'review_group_id',
        'legislature_id',
        'jurisdiction_id',
        'country_id',
        'created_by',
        'title',
        'slug',
        'description',
        'status',
        'current_step_number',
        'start_date',
        'completed_at',
    ];

    public function reviewGroup(): BelongsTo
    {
        return $this->belongsTo(ReviewGroup::class);
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

    public function ownerMembership(): HasOne
    {
        return $this->hasOne(PlsReviewMembership::class)
            ->where('role', PlsReviewMembershipRole::Owner->value);
    }

    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            PlsReviewMembership::class,
            'pls_review_id',
            'id',
            'id',
            'user_id',
        )->where('pls_review_memberships.role', PlsReviewMembershipRole::Owner->value);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(PlsReviewMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(PlsReviewInvitation::class);
    }

    public function pendingInvitations(): HasMany
    {
        return $this->hasMany(PlsReviewInvitation::class)->whereNull('accepted_at');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pls_review_memberships')
            ->withPivot(['role', 'invited_by'])
            ->withTimestamps();
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

    public function assignmentLabel(): string
    {
        return $this->reviewGroup?->name
            ?? $this->legislature?->name
            ?? $this->jurisdiction?->name
            ?? __('Unassigned');
    }

    /**
     * @return list<string>
     */
    public function assignmentLocationParts(): array
    {
        if ($this->review_group_id !== null) {
            return array_values(array_filter([
                $this->legislature?->name,
                $this->jurisdiction?->name,
                $this->country?->name,
            ]));
        }

        if ($this->legislature_id !== null) {
            return array_values(array_filter([
                $this->jurisdiction?->name,
                $this->country?->name,
            ]));
        }

        if ($this->jurisdiction_id !== null) {
            return array_values(array_filter([
                $this->country?->name,
            ]));
        }

        return [];
    }

    public function canBeViewedBy(User $user): bool
    {
        return $this->membershipFor($user) !== null;
    }

    public function canBeUpdatedBy(User $user): bool
    {
        $membership = $this->membershipFor($user);

        if ($membership === null) {
            return false;
        }

        return in_array($membership->role, [
            PlsReviewMembershipRole::Owner,
            PlsReviewMembershipRole::Contributor,
        ], true);
    }

    public function canManageCollaborators(User $user): bool
    {
        return $this->membershipFor($user)?->role === PlsReviewMembershipRole::Owner;
    }

    public function membershipFor(User $user): ?PlsReviewMembership
    {
        if ($this->relationLoaded('memberships')) {
            /** @var ?PlsReviewMembership $membership */
            $membership = $this->memberships->firstWhere('user_id', $user->id);

            return $membership;
        }

        return $this->memberships()
            ->where('user_id', $user->id)
            ->first();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('memberships', function (Builder $membershipQuery) use ($user): void {
            $membershipQuery->where('user_id', $user->id);
        });
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
