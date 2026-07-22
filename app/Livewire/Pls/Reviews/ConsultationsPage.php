<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Consultations\Actions\StoreConsultation;
use App\Domain\Consultations\Actions\StoreConsultationMaterial;
use App\Domain\Consultations\Actions\StoreSubmission;
use App\Domain\Consultations\Actions\UpdateConsultation;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\ConsultationMaterial;
use App\Domain\Consultations\Enums\ConsultationMaterialType;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use App\Jobs\ProcessReviewDocument;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ConsultationsPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'consultations';

    public string $consultationEditingId = '';

    public string $consultationTitle = '';

    public string $consultationType = ConsultationType::Hearing->value;

    /**
     * @var list<string>
     */
    public array $consultationTypesToPlan = [ConsultationType::Hearing->value];

    public string $consultationHeldAt = '';

    public string $consultationSummary = '';

    public string $consultationDocumentId = '';

    public string $consultationMaterialConsultationId = '';

    public string $consultationMaterialTitle = '';

    public string $consultationMaterialType = ConsultationMaterialType::HearingTranscript->value;

    public string $consultationMaterialStakeholderId = '';

    public ?TemporaryUploadedFile $consultationMaterialUpload = null;

    #[Url(as: 'stakeholder')]
    public string $submissionStakeholderId = '';

    public string $submissionDocumentId = '';

    public string $submissionSubmittedAt = '';

    public string $submissionSummary = '';

    public string $submissionAutoFilledSummary = '';

    public bool $showAddConsultationModal = false;

    public bool $showEditConsultationModal = false;

    public bool $showAddSubmissionModal = false;

    public bool $showAddConsultationMaterialModal = false;

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
            'consultationMaterialTypes' => ConsultationMaterialType::cases(),
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

    public function prepareConsultationMaterialUpload(int $consultationId): void
    {
        $this->authorizeReviewMutation();

        if (! $this->review->consultations()->whereKey($consultationId)->exists()) {
            return;
        }

        $this->resetConsultationMaterialForm();
        $this->consultationMaterialConsultationId = (string) $consultationId;
        $this->showAddConsultationMaterialModal = true;
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

        $methodsToPlan = $this->consultationTypesToPlan;

        if (
            $methodsToPlan === [ConsultationType::Hearing->value]
            && $this->consultationType !== ConsultationType::Hearing->value
        ) {
            $methodsToPlan = [$this->consultationType];
        }

        $methods = collect($methodsToPlan)
            ->map(fn (mixed $type): ?ConsultationType => is_string($type) ? ConsultationType::tryFrom($type) : null)
            ->filter()
            ->unique(fn (ConsultationType $type): string => $type->value)
            ->values();

        if ($methods->isEmpty()) {
            $this->addError('consultationTypesToPlan', __('Choose at least one consultation method.'));

            return;
        }

        try {
            foreach ($methods as $method) {
                $action->store([
                    'pls_review_id' => $this->review->id,
                    'title' => $this->consultationTitleForMethod($method, $methods->count()),
                    'consultation_type' => $method->value,
                    'held_at' => $this->blankToNull($this->consultationHeldAt),
                    'summary' => $this->consultationSummary,
                    'document_id' => $methods->count() === 1 && $this->blankToNull($this->consultationDocumentId) !== null
                        ? (int) $this->consultationDocumentId
                        : null,
                ]);
            }

            $this->review = $this->loadReview();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'consultationTitle',
                'consultation_type' => 'consultationTypesToPlan',
                'held_at' => 'consultationHeldAt',
                'summary' => 'consultationSummary',
                'document_id' => 'consultationDocumentId',
            ]);

            return;
        }

        $this->resetConsultationForm();
        $this->showAddConsultationModal = false;

        $this->dispatchWorkspaceToast(Toast::success(
            $methods->count() === 1 ? __('Consultation added') : __('Consultations planned'),
            $methods->count() === 1
                ? __('Consultation activity added to the review.')
                : __('Consultation activities added to the review.'),
        ));
    }

    public function storeConsultationMaterial(StoreConsultationMaterial $action): void
    {
        $this->authorizeReviewMutation();

        $this->validate([
            'consultationMaterialConsultationId' => ['required', 'integer'],
            'consultationMaterialTitle' => ['nullable', 'string', 'max:255'],
            'consultationMaterialType' => ['required', 'string'],
            'consultationMaterialStakeholderId' => ['nullable', 'integer'],
            'consultationMaterialUpload' => ['required', 'file', 'mimes:pdf,docx,txt,md', 'max:51200'],
        ], [
            'consultationMaterialUpload.required' => __('Choose a result file to upload.'),
            'consultationMaterialUpload.mimes' => __('Choose a PDF, DOCX, TXT, or MD file.'),
            'consultationMaterialUpload.max' => __('Choose a file that is 50 MB or smaller.'),
        ]);

        try {
            $material = $action->store([
                'pls_review_id' => $this->review->id,
                'consultation_id' => (int) $this->consultationMaterialConsultationId,
                'stakeholder_id' => $this->blankToNull($this->consultationMaterialStakeholderId) === null ? null : (int) $this->consultationMaterialStakeholderId,
                'material_type' => $this->consultationMaterialType,
                'title' => $this->consultationMaterialTitle !== ''
                    ? $this->consultationMaterialTitle
                    : $this->consultationMaterialUpload->getClientOriginalName(),
                'file' => $this->consultationMaterialUpload,
            ]);
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'consultation_id' => 'consultationMaterialConsultationId',
                'stakeholder_id' => 'consultationMaterialStakeholderId',
                'material_type' => 'consultationMaterialType',
            ]);

            return;
        }

        ProcessReviewDocument::dispatch($material->document_id);
        $this->review = $this->loadReview();
        $this->resetConsultationMaterialForm();
        $this->showAddConsultationMaterialModal = false;

        $this->dispatchWorkspaceToast(Toast::warning(
            __('Results added'),
            __('PLSAssist is reading the uploaded consultation result in the background.'),
        ));
    }

    public function askAssistantForConsultationInsights(int $consultationId): void
    {
        $consultation = $this->loadReview()->consultations
            ->firstWhere('id', $consultationId);

        if (! $consultation instanceof Consultation || $consultation->materials->isEmpty()) {
            return;
        }

        $sourceTitles = $consultation->materials
            ->map(fn (ConsultationMaterial $material): string => $material->document?->title ?? '')
            ->filter()
            ->unique()
            ->values()
            ->implode('; ');

        $this->dispatch('assistant-prompt-requested', prompt: sprintf(
            'Using only the uploaded results linked to the consultation "%s" (%s), draft a concise, source-grounded summary of what this consultation is revealing. Separate recurring themes, areas of agreement or difference, and gaps in perspective. Do not infer public opinion or representativeness beyond the uploaded material.',
            $consultation->title,
            $sourceTitles,
        ));
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

        $this->dispatchWorkspaceToast(Toast::success(
            __('Consultation updated'),
            __('Consultation activity updated.'),
        ));
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

        $this->dispatchWorkspaceToast(Toast::success(
            __('Submission logged'),
            __('Submission logged for this review.'),
        ));
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
                'consultations.materials.document',
                'consultations.materials.stakeholder',
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
        $this->consultationTypesToPlan = [ConsultationType::Hearing->value];

        $this->resetValidation([
            'consultationEditingId',
            'consultationTitle',
            'consultationType',
            'consultationTypesToPlan',
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

    private function resetConsultationMaterialForm(): void
    {
        $this->reset([
            'consultationMaterialConsultationId',
            'consultationMaterialTitle',
            'consultationMaterialStakeholderId',
            'consultationMaterialUpload',
        ]);

        $this->consultationMaterialType = ConsultationMaterialType::HearingTranscript->value;

        $this->resetValidation([
            'consultationMaterialConsultationId',
            'consultationMaterialTitle',
            'consultationMaterialType',
            'consultationMaterialStakeholderId',
            'consultationMaterialUpload',
        ]);
    }

    public function consultationTypeLabel(ConsultationType|string $type): string
    {
        $value = $type instanceof ConsultationType ? $type->value : $type;

        return match ($value) {
            ConsultationType::FocusGroup->value => __('Focus group'),
            ConsultationType::PublicConsultation->value => __('Public consultation'),
            ConsultationType::PublicCommentPeriod->value => __('Public comment period'),
            default => Str::headline($value),
        };
    }

    public function consultationMaterialTypeLabel(ConsultationMaterialType $type): string
    {
        return match ($type) {
            ConsultationMaterialType::FocusGroupNotes => __('Focus group notes'),
            ConsultationMaterialType::PublicComments => __('Public comments'),
            ConsultationMaterialType::SurveyResults => __('Survey results'),
            ConsultationMaterialType::WrittenSubmission => __('Written submission'),
            default => Str::headline($type->value),
        };
    }

    /**
     * @return array{count: int, analyzed_count: int, processing_count: int, themes: list<string>}
     */
    public function consultationInsight(Consultation $consultation): array
    {
        $materials = $consultation->materials->filter(fn (ConsultationMaterial $material): bool => $material->document instanceof Document);
        $analyzedMaterials = $materials->filter(fn (ConsultationMaterial $material): bool => data_get($material->document->metadata, 'document_analysis.status') === 'saved');

        return [
            'count' => $materials->count(),
            'analyzed_count' => $analyzedMaterials->count(),
            'processing_count' => $materials->count() - $analyzedMaterials->count(),
            'themes' => $analyzedMaterials
                ->flatMap(fn (ConsultationMaterial $material): array => data_get($material->document->metadata, 'document_analysis.key_themes', []))
                ->filter(fn (mixed $theme): bool => is_string($theme) && trim($theme) !== '')
                ->map(fn (string $theme): string => trim($theme))
                ->unique(fn (string $theme): string => Str::lower($theme))
                ->take(6)
                ->values()
                ->all(),
        ];
    }

    private function consultationTitleForMethod(ConsultationType $method, int $methodCount): string
    {
        return $methodCount === 1
            ? $this->consultationTitle
            : sprintf('%s - %s', $this->consultationTitle, $this->consultationTypeLabel($method));
    }
}
