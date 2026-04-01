<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Consultations\Actions\StoreConsultation;
use App\Domain\Consultations\Actions\StoreSubmission;
use App\Domain\Consultations\Actions\UpdateConsultation;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
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

    public string $submissionAutoFilledSummary = '';

    public bool $showAddConsultationModal = false;

    public bool $showEditConsultationModal = false;

    public bool $showAddSubmissionModal = false;

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.consultations-page', [
            'review' => $review,
            'availableDocuments' => $this->availableDocuments($review),
            'consultationTypes' => ConsultationType::cases(),
            'consultations' => $this->consultations($review),
            'consultationStep' => $review->steps->firstWhere('step_key', 'consultations'),
            'selectedSubmissionStakeholder' => $review->stakeholders->firstWhere('id', (int) $this->submissionStakeholderId),
        ], $review);
    }

    public function prepareConsultationCreate(): void
    {
        $this->resetConsultationForm();
        $this->showEditConsultationModal = false;
        $this->showAddConsultationModal = true;
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

        $this->showAddSubmissionModal = true;
    }

    public function updatedSubmissionDocumentId(string $value): void
    {
        $currentSummary = trim($this->submissionSummary);
        $autoFilledSummary = trim($this->submissionAutoFilledSummary);
        $shouldReplaceSummary = $currentSummary === ''
            || ($autoFilledSummary !== '' && $currentSummary === $autoFilledSummary);

        if (! $shouldReplaceSummary) {
            return;
        }

        $selectedDocument = $this->availableDocuments($this->loadReview())
            ->firstWhere('id', (int) $value);

        if (! $selectedDocument instanceof Document) {
            $this->submissionSummary = '';
            $this->submissionAutoFilledSummary = '';

            return;
        }

        $documentSummary = trim((string) ($selectedDocument->summary ?? ''));

        if ($documentSummary === '') {
            $this->submissionSummary = '';
            $this->submissionAutoFilledSummary = '';

            return;
        }

        $this->submissionSummary = $selectedDocument->summary ?? '';
        $this->submissionAutoFilledSummary = $this->submissionSummary;
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
        $this->showAddConsultationModal = false;

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

        $this->showAddConsultationModal = false;
        $this->showEditConsultationModal = true;
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
        $this->showEditConsultationModal = false;

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
        $this->showAddSubmissionModal = false;

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
                'stakeholders',
                'consultations.document',
                'submissions.stakeholder',
                'submissions.document',
            ])
            ->findOrFail($this->review->getKey());
    }

    /**
     * @return Collection<int, Consultation>
     */
    private function consultations(PlsReview $review): Collection
    {
        $completedConsultations = $review->consultations
            ->filter(fn ($consultation): bool => $consultation->held_at !== null)
            ->sortByDesc('held_at');

        $plannedConsultations = $review->consultations
            ->filter(fn ($consultation): bool => $consultation->held_at === null)
            ->sortBy('title');

        return $completedConsultations
            ->concat($plannedConsultations)
            ->values();
    }

    /**
     * @return Collection<int, Document>
     */
    private function availableDocuments(PlsReview $review): Collection
    {
        return $review->documents
            ->reject(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)
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
            'submissionAutoFilledSummary',
        ]);

        $this->resetValidation([
            'submissionStakeholderId',
            'submissionDocumentId',
            'submissionSubmittedAt',
            'submissionSummary',
        ]);
    }
}
