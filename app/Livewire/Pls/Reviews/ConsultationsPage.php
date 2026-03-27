<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Consultations\Actions\StoreConsultation;
use App\Domain\Consultations\Actions\StoreSubmission;
use App\Domain\Consultations\Actions\UpdateConsultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ConsultationsPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'consultations';

    public string $consultationEditingId = '';

    public string $consultationTitle = '';

    public string $consultationType = ConsultationType::Hearing->value;

    public string $consultationHeldAt = '';

    public string $consultationSummary = '';

    public string $consultationDocumentId = '';

    #[Url(as: 'stakeholder')]
    public string $submissionStakeholderId = '';

    public string $submissionDocumentId = '';

    public string $submissionSubmittedAt = '';

    public string $submissionSummary = '';

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.consultations-page', [
            'review' => $review,
            'consultationTypes' => ConsultationType::cases(),
            'completedConsultations' => $this->completedConsultations($review),
            'plannedConsultations' => $this->plannedConsultations($review),
            'stakeholdersWithSubmissions' => $this->stakeholdersWithSubmissions($review),
            'stakeholdersAwaitingEvidence' => $this->stakeholdersAwaitingEvidence($review),
            'consultationStep' => $review->steps->firstWhere('step_key', 'consultations'),
            'selectedSubmissionStakeholder' => $review->stakeholders->firstWhere('id', (int) $this->submissionStakeholderId),
        ], $review);
    }

    public function prepareConsultationCreate(): void
    {
        $this->resetConsultationForm();
    }

    #[On('prepare-consultation-submission-create')]
    public function prepareSubmissionCreate(?int $stakeholderId = null): void
    {
        $this->resetSubmissionForm();

        if (
            $stakeholderId !== null
            && $this->review->stakeholders()->whereKey($stakeholderId)->exists()
        ) {
            $this->submissionStakeholderId = (string) $stakeholderId;
        }
    }

    public function storeConsultation(StoreConsultation $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->consultationTitle,
                'consultation_type' => $this->consultationType,
                'held_at' => $this->blankToNull($this->consultationHeldAt),
                'summary' => $this->consultationSummary,
                'document_id' => $this->blankToNull($this->consultationDocumentId) === null ? null : (int) $this->consultationDocumentId,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'consultationTitle',
                'consultation_type' => 'consultationType',
                'held_at' => 'consultationHeldAt',
                'summary' => 'consultationSummary',
                'document_id' => 'consultationDocumentId',
            ]);

            return;
        }

        $this->resetConsultationForm();

        $this->dispatch('review-workspace-updated', status: __('Consultation activity added to the review.'));
    }

    public function startEditingConsultation(int $consultationId): void
    {
        $this->authorizeReviewMutation();

        $consultation = $this->review->consultations()
            ->whereKey($consultationId)
            ->first();

        if ($consultation === null) {
            return;
        }

        $this->consultationEditingId = (string) $consultation->id;
        $this->consultationTitle = $consultation->title;
        $this->consultationType = $consultation->consultation_type->value;
        $this->consultationHeldAt = $consultation->held_at?->format('Y-m-d') ?? '';
        $this->consultationSummary = $consultation->summary;
        $this->consultationDocumentId = $consultation->document_id === null ? '' : (string) $consultation->document_id;

        $this->resetValidation([
            'consultationEditingId',
            'consultationTitle',
            'consultationType',
            'consultationHeldAt',
            'consultationSummary',
            'consultationDocumentId',
        ]);
    }

    public function updateConsultation(UpdateConsultation $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'consultation_id' => $this->consultationEditingId,
                'pls_review_id' => $this->review->id,
                'title' => $this->consultationTitle,
                'consultation_type' => $this->consultationType,
                'held_at' => $this->blankToNull($this->consultationHeldAt),
                'summary' => $this->consultationSummary,
                'document_id' => $this->blankToNull($this->consultationDocumentId) === null ? null : (int) $this->consultationDocumentId,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'consultation_id' => 'consultationEditingId',
                'title' => 'consultationTitle',
                'consultation_type' => 'consultationType',
                'held_at' => 'consultationHeldAt',
                'summary' => 'consultationSummary',
                'document_id' => 'consultationDocumentId',
            ]);

            return;
        }

        $this->resetConsultationForm();

        $this->dispatch('review-workspace-updated', status: __('Consultation activity updated.'));
    }

    public function storeSubmission(StoreSubmission $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'stakeholder_id' => $this->submissionStakeholderId,
                'document_id' => $this->blankToNull($this->submissionDocumentId) === null ? null : (int) $this->submissionDocumentId,
                'submitted_at' => $this->blankToNull($this->submissionSubmittedAt),
                'summary' => $this->submissionSummary,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'stakeholder_id' => 'submissionStakeholderId',
                'document_id' => 'submissionDocumentId',
                'submitted_at' => 'submissionSubmittedAt',
                'summary' => 'submissionSummary',
            ]);

            return;
        }

        $this->resetSubmissionForm();

        $this->dispatch('review-workspace-updated', status: __('Submission logged for this review.'));
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mapValidationErrors(ValidationException $exception, array $mapping): void
    {
        $this->resetValidation(array_values($mapping));

        foreach ($exception->errors() as $key => $messages) {
            $mappedKey = $mapping[$key] ?? $key;

            foreach ($messages as $message) {
                $this->addError($mappedKey, $message);
            }
        }
    }

    private function authorizeReviewMutation(): void
    {
        $this->authorize('update', $this->review);
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'steps',
                'documents',
                'stakeholders.submissions',
                'consultations.document',
                'submissions.stakeholder',
                'submissions.document',
            ])
            ->findOrFail($this->review->getKey());
    }

    /**
     * @return EloquentCollection<int, \App\Domain\Consultations\Consultation>
     */
    private function completedConsultations(PlsReview $review): EloquentCollection
    {
        return $review->consultations
            ->filter(fn ($consultation): bool => $consultation->held_at !== null)
            ->sortByDesc('held_at');
    }

    /**
     * @return EloquentCollection<int, \App\Domain\Consultations\Consultation>
     */
    private function plannedConsultations(PlsReview $review): EloquentCollection
    {
        return $review->consultations
            ->filter(fn ($consultation): bool => $consultation->held_at === null)
            ->sortBy('title');
    }

    /**
     * @return EloquentCollection<int, Stakeholder>
     */
    private function stakeholdersWithSubmissions(PlsReview $review): EloquentCollection
    {
        return $review->stakeholders
            ->filter(fn (Stakeholder $stakeholder): bool => $stakeholder->submissions->isNotEmpty())
            ->values();
    }

    /**
     * @return EloquentCollection<int, Stakeholder>
     */
    private function stakeholdersAwaitingEvidence(PlsReview $review): EloquentCollection
    {
        return $review->stakeholders
            ->filter(fn (Stakeholder $stakeholder): bool => $stakeholder->submissions->isEmpty())
            ->values();
    }

    private function resetConsultationForm(): void
    {
        $this->reset([
            'consultationEditingId',
            'consultationTitle',
            'consultationHeldAt',
            'consultationSummary',
            'consultationDocumentId',
        ]);

        $this->consultationType = ConsultationType::Hearing->value;

        $this->resetValidation([
            'consultationEditingId',
            'consultationTitle',
            'consultationType',
            'consultationHeldAt',
            'consultationSummary',
            'consultationDocumentId',
        ]);
    }

    private function resetSubmissionForm(): void
    {
        $this->reset([
            'submissionStakeholderId',
            'submissionDocumentId',
            'submissionSubmittedAt',
            'submissionSummary',
        ]);

        $this->resetValidation([
            'submissionStakeholderId',
            'submissionDocumentId',
            'submissionSubmittedAt',
            'submissionSummary',
        ]);
    }
}
