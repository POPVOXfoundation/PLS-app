<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Analysis\Actions\StoreFinding;
use App\Domain\Analysis\Actions\StoreRecommendation;
use App\Domain\Analysis\Actions\UpdateFinding;
use App\Domain\Analysis\Actions\UpdateRecommendation;
use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Consultations\Actions\StoreConsultation;
use App\Domain\Consultations\Actions\StoreSubmission;
use App\Domain\Consultations\Actions\UpdateConsultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Actions\UpdateReviewDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Legislation\Actions\AttachLegislationToReview;
use App\Domain\Legislation\Actions\CreateLegislationForReview;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reporting\Actions\StoreReport;
use App\Domain\Reporting\Actions\StoreGovernmentResponse;
use App\Domain\Reporting\Actions\UpdateReport;
use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use App\Domain\Stakeholders\Actions\StoreImplementingAgency;
use App\Domain\Stakeholders\Actions\StoreStakeholder;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public PlsReview $review;

    public int $selectedStepNumber = 1;

    public string $attachLegislationId = '';

    public string $attachLegislationRelationshipType = ReviewLegislationRelationshipType::Related->value;

    public string $newLegislationTitle = '';

    public string $newLegislationShortTitle = '';

    public string $newLegislationType = LegislationType::Act->value;

    public string $newLegislationDateEnacted = '';

    public string $newLegislationSummary = '';

    public string $newLegislationRelationshipType = ReviewLegislationRelationshipType::Primary->value;

    public string $documentTitle = '';

    public string $documentType = DocumentType::CommitteeReport->value;

    public string $documentEditingId = '';

    public string $documentStoragePath = '';

    public string $documentMimeType = 'application/pdf';

    public string $documentFileSize = '';

    public string $documentSummary = '';

    public TemporaryUploadedFile|null $documentUpload = null;

    public string $consultationEditingId = '';

    public string $consultationTitle = '';

    public string $consultationType = ConsultationType::Hearing->value;

    public string $consultationHeldAt = '';

    public string $consultationSummary = '';

    public string $consultationDocumentId = '';

    public string $submissionStakeholderId = '';

    public string $submissionDocumentId = '';

    public string $submissionSubmittedAt = '';

    public string $submissionSummary = '';

    public string $stakeholderTypeFilter = 'all';

    public string $stakeholderName = '';

    public string $stakeholderType = StakeholderType::GovernmentAgency->value;

    public string $stakeholderOrganization = '';

    public string $stakeholderEmail = '';

    public string $stakeholderPhone = '';

    public string $implementingAgencyName = '';

    public string $implementingAgencyType = ImplementingAgencyType::Agency->value;

    public string $findingTitle = '';

    public string $findingType = FindingType::ImplementationGap->value;

    public string $findingEditingId = '';

    public string $findingSummary = '';

    public string $findingDetail = '';

    public string $recommendationFindingId = '';

    public string $recommendationEditingId = '';

    public string $recommendationTitle = '';

    public string $recommendationDescription = '';

    public string $recommendationType = RecommendationType::ImproveImplementation->value;

    public string $reportTitle = '';

    public string $reportType = ReportType::DraftReport->value;

    public string $reportStatus = ReportStatus::Draft->value;

    public string $reportEditingId = '';

    public string $reportDocumentId = '';

    public string $reportPublishedAt = '';

    public string $governmentResponseReportId = '';

    public string $governmentResponseDocumentId = '';

    public string $governmentResponseStatus = GovernmentResponseStatus::Requested->value;

    public string $governmentResponseReceivedAt = '';

    public string $governmentResponseSummary = '';

    public function mount(PlsReview $review): void
    {
        $this->authorize('view', $review);

        $this->review = $review;
        $this->selectedStepNumber = max(1, $review->current_step_number);
    }

    public function render(): View
    {
        $review = $this->loadReview();
        $selectedStep = $review->steps->firstWhere('step_number', $this->selectedStepNumber) ?? $review->steps->first();

        if ($selectedStep !== null && $selectedStep->step_number !== $this->selectedStepNumber) {
            $this->selectedStepNumber = $selectedStep->step_number;
        }

        return view('livewire.pls.reviews.show', [
            'review' => $review,
            'selectedStep' => $selectedStep,
            'attachableLegislation' => $this->attachableLegislation($review),
            'legislationTypes' => LegislationType::cases(),
            'legislationRelationshipTypes' => ReviewLegislationRelationshipType::cases(),
            'documentTypes' => DocumentType::cases(),
            'consultationTypes' => ConsultationType::cases(),
            'stakeholderTypes' => StakeholderType::cases(),
            'filteredStakeholders' => $this->filteredStakeholders($review),
            'implementingAgencyTypes' => ImplementingAgencyType::cases(),
            'findingTypes' => FindingType::cases(),
            'recommendationTypes' => RecommendationType::cases(),
            'reportTypes' => ReportType::cases(),
            'reportStatuses' => ReportStatus::cases(),
            'governmentResponseStatuses' => GovernmentResponseStatus::cases(),
            'workspaceGuidance' => $this->workspaceGuidance($review),
        ])->layout('layouts.app', [
            'title' => $review->title,
        ]);
    }

    public function selectStep(int $stepNumber): void
    {
        if (! $this->review->steps()->where('step_number', $stepNumber)->exists()) {
            return;
        }

        $this->selectedStepNumber = $stepNumber;
    }

    public function attachLegislation(AttachLegislationToReview $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->attach([
                'pls_review_id' => $this->review->id,
                'legislation_id' => $this->attachLegislationId,
                'relationship_type' => $this->attachLegislationRelationshipType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'legislation_id' => 'attachLegislationId',
                'relationship_type' => 'attachLegislationRelationshipType',
            ]);

            return;
        }

        $this->reset('attachLegislationId');
        $this->resetValidation(['attachLegislationId']);

        session()->flash('status', __('Legislation linked to the review.'));
    }

    public function createLegislation(CreateLegislationForReview $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->create([
                'pls_review_id' => $this->review->id,
                'title' => $this->newLegislationTitle,
                'short_title' => $this->blankToNull($this->newLegislationShortTitle),
                'legislation_type' => $this->newLegislationType,
                'date_enacted' => $this->blankToNull($this->newLegislationDateEnacted),
                'summary' => $this->blankToNull($this->newLegislationSummary),
                'relationship_type' => $this->newLegislationRelationshipType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'newLegislationTitle',
                'short_title' => 'newLegislationShortTitle',
                'legislation_type' => 'newLegislationType',
                'date_enacted' => 'newLegislationDateEnacted',
                'summary' => 'newLegislationSummary',
                'relationship_type' => 'newLegislationRelationshipType',
            ]);

            return;
        }

        $this->resetLegislationForm();

        session()->flash('status', __('Legislation created and linked to the review.'));
    }

    public function storeDocument(StoreReviewDocumentMetadata $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->documentTitle,
                'document_type' => $this->documentType,
                'storage_path' => $this->blankToNull($this->documentStoragePath),
                'file' => $this->documentUpload,
                'mime_type' => $this->blankToNull($this->documentMimeType),
                'file_size' => $this->blankToNull($this->documentFileSize) === null ? null : (int) $this->documentFileSize,
                'summary' => $this->blankToNull($this->documentSummary),
                'metadata' => null,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'documentTitle',
                'document_type' => 'documentType',
                'storage_path' => 'documentStoragePath',
                'file' => 'documentUpload',
                'mime_type' => 'documentMimeType',
                'file_size' => 'documentFileSize',
                'summary' => 'documentSummary',
            ]);

            return;
        }

        $this->resetDocumentForm();

        session()->flash('status', __('Document added to the review.'));
    }

    public function startEditingDocument(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $document = $this->review->documents()
            ->whereKey($documentId)
            ->first();

        if ($document === null) {
            return;
        }

        $this->documentEditingId = (string) $document->id;
        $this->documentTitle = $document->title;
        $this->documentType = $document->document_type->value;
        $this->documentStoragePath = $document->storage_path;
        $this->documentMimeType = $document->mime_type ?? '';
        $this->documentFileSize = $document->file_size === null ? '' : (string) $document->file_size;
        $this->documentSummary = $document->summary ?? '';
        $this->documentUpload = null;

        $this->resetValidation([
            'documentEditingId',
            'documentTitle',
            'documentType',
            'documentStoragePath',
            'documentUpload',
            'documentMimeType',
            'documentFileSize',
            'documentSummary',
        ]);
    }

    public function updateDocument(UpdateReviewDocument $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'document_id' => $this->documentEditingId,
                'pls_review_id' => $this->review->id,
                'title' => $this->documentTitle,
                'document_type' => $this->documentType,
                'storage_path' => $this->blankToNull($this->documentStoragePath),
                'file' => $this->documentUpload,
                'mime_type' => $this->blankToNull($this->documentMimeType),
                'file_size' => $this->blankToNull($this->documentFileSize) === null ? null : (int) $this->documentFileSize,
                'summary' => $this->blankToNull($this->documentSummary),
                'metadata' => null,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'document_id' => 'documentEditingId',
                'title' => 'documentTitle',
                'document_type' => 'documentType',
                'storage_path' => 'documentStoragePath',
                'file' => 'documentUpload',
                'mime_type' => 'documentMimeType',
                'file_size' => 'documentFileSize',
                'summary' => 'documentSummary',
            ]);

            return;
        }

        $this->resetDocumentForm();

        session()->flash('status', __('Document updated.'));
    }

    public function deleteDocument(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $document = $this->review->documents()
            ->whereKey($documentId)
            ->first();

        if ($document === null) {
            return;
        }

        if ($document->storage_path !== '') {
            Storage::disk(config('filesystems.default'))->delete($document->storage_path);
        }

        $document->delete();
        $this->review = $this->loadReview();

        if ($this->documentEditingId === (string) $documentId) {
            $this->resetDocumentForm();
        }

        session()->flash('status', __('Document removed from the review.'));
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

        session()->flash('status', __('Consultation activity added to the review.'));
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

        session()->flash('status', __('Consultation activity updated.'));
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

        session()->flash('status', __('Submission logged for this review.'));
    }

    public function storeStakeholder(StoreStakeholder $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'name' => $this->stakeholderName,
                'stakeholder_type' => $this->stakeholderType,
                'contact_details' => [
                    'organization' => $this->blankToNull($this->stakeholderOrganization),
                    'email' => $this->blankToNull($this->stakeholderEmail),
                    'phone' => $this->blankToNull($this->stakeholderPhone),
                ],
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'name' => 'stakeholderName',
                'stakeholder_type' => 'stakeholderType',
                'contact_details.organization' => 'stakeholderOrganization',
                'contact_details.email' => 'stakeholderEmail',
                'contact_details.phone' => 'stakeholderPhone',
            ]);

            return;
        }

        $this->resetStakeholderForm();

        session()->flash('status', __('Stakeholder added to the review.'));
    }

    public function storeImplementingAgency(StoreImplementingAgency $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'name' => $this->implementingAgencyName,
                'agency_type' => $this->implementingAgencyType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'name' => 'implementingAgencyName',
                'agency_type' => 'implementingAgencyType',
            ]);

            return;
        }

        $this->resetImplementingAgencyForm();

        session()->flash('status', __('Implementing agency added to the review.'));
    }

    public function storeFinding(StoreFinding $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->findingTitle,
                'finding_type' => $this->findingType,
                'summary' => $this->blankToNull($this->findingSummary),
                'detail' => $this->blankToNull($this->findingDetail),
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'findingTitle',
                'finding_type' => 'findingType',
                'summary' => 'findingSummary',
                'detail' => 'findingDetail',
            ]);

            return;
        }

        $this->resetFindingForm();

        session()->flash('status', __('Finding added to the review.'));
    }

    public function startEditingFinding(int $findingId): void
    {
        $this->authorizeReviewMutation();

        $finding = $this->review->findings()
            ->whereKey($findingId)
            ->first();

        if ($finding === null) {
            return;
        }

        $this->findingEditingId = (string) $finding->id;
        $this->findingTitle = $finding->title;
        $this->findingType = $finding->finding_type->value;
        $this->findingSummary = $finding->summary ?? '';
        $this->findingDetail = $finding->detail ?? '';

        $this->resetValidation([
            'findingEditingId',
            'findingTitle',
            'findingType',
            'findingSummary',
            'findingDetail',
        ]);
    }

    public function updateFinding(UpdateFinding $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'finding_id' => $this->findingEditingId,
                'pls_review_id' => $this->review->id,
                'title' => $this->findingTitle,
                'finding_type' => $this->findingType,
                'summary' => $this->blankToNull($this->findingSummary),
                'detail' => $this->blankToNull($this->findingDetail),
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'finding_id' => 'findingEditingId',
                'title' => 'findingTitle',
                'finding_type' => 'findingType',
                'summary' => 'findingSummary',
                'detail' => 'findingDetail',
            ]);

            return;
        }

        $this->resetFindingForm();

        session()->flash('status', __('Finding updated.'));
    }

    public function deleteFinding(int $findingId): void
    {
        $this->authorizeReviewMutation();

        $finding = $this->review->findings()
            ->whereKey($findingId)
            ->first();

        if ($finding === null) {
            return;
        }

        $finding->delete();
        $this->review = $this->loadReview();

        if ($this->findingEditingId === (string) $findingId) {
            $this->resetFindingForm();
        }

        session()->flash('status', __('Finding removed from the review.'));
    }

    public function storeRecommendation(StoreRecommendation $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'finding_id' => $this->recommendationFindingId,
                'title' => $this->recommendationTitle,
                'description' => $this->blankToNull($this->recommendationDescription),
                'recommendation_type' => $this->recommendationType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'finding_id' => 'recommendationFindingId',
                'title' => 'recommendationTitle',
                'description' => 'recommendationDescription',
                'recommendation_type' => 'recommendationType',
            ]);

            return;
        }

        $this->resetRecommendationForm();

        session()->flash('status', __('Recommendation added to the review.'));
    }

    public function startEditingRecommendation(int $recommendationId): void
    {
        $this->authorizeReviewMutation();

        $recommendation = $this->review->recommendations()
            ->whereKey($recommendationId)
            ->first();

        if ($recommendation === null) {
            return;
        }

        $this->recommendationEditingId = (string) $recommendation->id;
        $this->recommendationFindingId = (string) $recommendation->finding_id;
        $this->recommendationTitle = $recommendation->title;
        $this->recommendationDescription = $recommendation->description ?? '';
        $this->recommendationType = $recommendation->recommendation_type->value;

        $this->resetValidation([
            'recommendationEditingId',
            'recommendationFindingId',
            'recommendationTitle',
            'recommendationDescription',
            'recommendationType',
        ]);
    }

    public function updateRecommendation(UpdateRecommendation $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'recommendation_id' => $this->recommendationEditingId,
                'pls_review_id' => $this->review->id,
                'finding_id' => $this->recommendationFindingId,
                'title' => $this->recommendationTitle,
                'description' => $this->blankToNull($this->recommendationDescription),
                'recommendation_type' => $this->recommendationType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'recommendation_id' => 'recommendationEditingId',
                'finding_id' => 'recommendationFindingId',
                'title' => 'recommendationTitle',
                'description' => 'recommendationDescription',
                'recommendation_type' => 'recommendationType',
            ]);

            return;
        }

        $this->resetRecommendationForm();

        session()->flash('status', __('Recommendation updated.'));
    }

    public function deleteRecommendation(int $recommendationId): void
    {
        $this->authorizeReviewMutation();

        $recommendation = $this->review->recommendations()
            ->whereKey($recommendationId)
            ->first();

        if ($recommendation === null) {
            return;
        }

        $recommendation->delete();
        $this->review = $this->loadReview();

        if ($this->recommendationEditingId === (string) $recommendationId) {
            $this->resetRecommendationForm();
        }

        session()->flash('status', __('Recommendation removed from the review.'));
    }

    public function storeReport(StoreReport $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->reportTitle,
                'report_type' => $this->reportType,
                'status' => $this->reportStatus,
                'document_id' => $this->blankToNull($this->reportDocumentId) === null ? null : (int) $this->reportDocumentId,
                'published_at' => $this->blankToNull($this->reportPublishedAt),
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'reportTitle',
                'report_type' => 'reportType',
                'status' => 'reportStatus',
                'document_id' => 'reportDocumentId',
                'published_at' => 'reportPublishedAt',
            ]);

            return;
        }

        $this->resetReportForm();

        session()->flash('status', __('Report record added to the review.'));
    }

    public function startEditingReport(int $reportId): void
    {
        $this->authorizeReviewMutation();

        $report = $this->review->reports()
            ->whereKey($reportId)
            ->first();

        if ($report === null) {
            return;
        }

        $this->reportEditingId = (string) $report->id;
        $this->reportTitle = $report->title;
        $this->reportType = $report->report_type->value;
        $this->reportStatus = $report->status->value;
        $this->reportDocumentId = $report->document_id === null ? '' : (string) $report->document_id;
        $this->reportPublishedAt = $report->published_at?->format('Y-m-d') ?? '';

        $this->resetValidation([
            'reportEditingId',
            'reportTitle',
            'reportType',
            'reportStatus',
            'reportDocumentId',
            'reportPublishedAt',
        ]);
    }

    public function updateReport(UpdateReport $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'report_id' => $this->reportEditingId,
                'pls_review_id' => $this->review->id,
                'title' => $this->reportTitle,
                'report_type' => $this->reportType,
                'status' => $this->reportStatus,
                'document_id' => $this->blankToNull($this->reportDocumentId) === null ? null : (int) $this->reportDocumentId,
                'published_at' => $this->blankToNull($this->reportPublishedAt),
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'report_id' => 'reportEditingId',
                'title' => 'reportTitle',
                'report_type' => 'reportType',
                'status' => 'reportStatus',
                'document_id' => 'reportDocumentId',
                'published_at' => 'reportPublishedAt',
            ]);

            return;
        }

        $this->resetReportForm();

        session()->flash('status', __('Report updated.'));
    }

    public function deleteReport(int $reportId): void
    {
        $this->authorizeReviewMutation();

        $report = $this->review->reports()
            ->whereKey($reportId)
            ->first();

        if ($report === null) {
            return;
        }

        $report->delete();
        $this->review = $this->loadReview();

        if ($this->reportEditingId === (string) $reportId) {
            $this->resetReportForm();
        }

        session()->flash('status', __('Report removed from the review.'));
    }

    public function storeGovernmentResponse(StoreGovernmentResponse $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'report_id' => $this->governmentResponseReportId,
                'document_id' => $this->blankToNull($this->governmentResponseDocumentId) === null ? null : (int) $this->governmentResponseDocumentId,
                'response_status' => $this->governmentResponseStatus,
                'received_at' => $this->blankToNull($this->governmentResponseReceivedAt),
                'summary' => $this->blankToNull($this->governmentResponseSummary),
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'report_id' => 'governmentResponseReportId',
                'document_id' => 'governmentResponseDocumentId',
                'response_status' => 'governmentResponseStatus',
                'received_at' => 'governmentResponseReceivedAt',
                'summary' => 'governmentResponseSummary',
            ]);

            return;
        }

        $this->resetGovernmentResponseForm();

        session()->flash('status', __('Government response recorded for this review.'));
    }

    public function stepContext(PlsReviewStep $step): string
    {
        return match ($step->step_key) {
            'define_scope' => __('Confirm the legislation under review, the committee mandate, and the boundaries of the inquiry.'),
            'background_data_plan' => __('Assemble source material, implementation records, and baseline evidence to guide the review.'),
            'stakeholder_plan' => __('Map the institutions and external actors that should inform the scrutiny process.'),
            'implementation_review' => __('Examine delivery agencies, delegated powers, and operational bottlenecks in implementation.'),
            'consultations' => __('Capture written and oral input from the public, experts, and implementing institutions.'),
            'analysis' => __('Synthesize evidence into findings and identify the strongest recommendation themes.'),
            'draft_report' => __('Translate the inquiry record into a committee report with clear conclusions and actions.'),
            'dissemination' => __('Track publication readiness, accessibility, and the materials needed for public release.'),
            'government_response' => __('Monitor whether the executive has responded and whether commitments are on record.'),
            'follow_up' => __('Keep sight of implementation progress after the report phase concludes.'),
            'evaluation' => __('Assess whether the review process produced usable lessons, evidence, and institutional value.'),
            default => __('Review the current materials attached to this workflow step.'),
        };
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function stepMetricCards(PlsReview $review, PlsReviewStep $step): array
    {
        return match ($step->step_key) {
            'define_scope' => [
                ['label' => __('Legislation linked'), 'value' => (string) $review->legislation->count()],
                ['label' => __('Objectives captured'), 'value' => (string) $review->legislationObjectives->count()],
                ['label' => __('Working documents'), 'value' => (string) $review->documents->count()],
            ],
            'background_data_plan', 'implementation_review' => [
                ['label' => __('Documents'), 'value' => (string) $review->documents->count()],
                ['label' => __('Evidence items'), 'value' => (string) $review->evidenceItems->count()],
                ['label' => __('Agencies reviewed'), 'value' => (string) $review->implementingAgencies->count()],
            ],
            'stakeholder_plan', 'consultations' => [
                ['label' => __('Stakeholders'), 'value' => (string) $review->stakeholders->count()],
                ['label' => __('Consultations'), 'value' => (string) $review->consultations->count()],
                ['label' => __('Submissions'), 'value' => (string) $review->submissions->count()],
            ],
            'analysis' => [
                ['label' => __('Findings'), 'value' => (string) $review->findings->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
                ['label' => __('Evidence items'), 'value' => (string) $review->evidenceItems->count()],
            ],
            'draft_report', 'dissemination' => [
                ['label' => __('Reports'), 'value' => (string) $review->reports->count()],
                ['label' => __('Final documents'), 'value' => (string) $review->documents->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
            'government_response', 'follow_up', 'evaluation' => [
                ['label' => __('Reports'), 'value' => (string) $review->reports->count()],
                ['label' => __('Responses'), 'value' => (string) $review->governmentResponses->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
            default => [
                ['label' => __('Documents'), 'value' => (string) $review->documents->count()],
                ['label' => __('Findings'), 'value' => (string) $review->findings->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
        };
    }

    public function latestGovernmentResponseForReport(Report $report): ?GovernmentResponse
    {
        /** @var ?GovernmentResponse $response */
        $response = $report->governmentResponses
            ->sortByDesc(
                fn (GovernmentResponse $governmentResponse): int => $governmentResponse->received_at?->timestamp
                    ?? $governmentResponse->created_at?->timestamp
                    ?? 0,
            )
            ->first();

        return $response;
    }

    public function reportResponseIndicator(Report $report): array
    {
        $response = $this->latestGovernmentResponseForReport($report);

        if ($response !== null) {
            return match ($response->response_status) {
                GovernmentResponseStatus::Received => [
                    'label' => __('Response received'),
                    'classes' => 'border-emerald-200/80 bg-emerald-50/80 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-300',
                ],
                GovernmentResponseStatus::Overdue => [
                    'label' => __('Response overdue'),
                    'classes' => 'border-rose-200/80 bg-rose-50/80 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-300',
                ],
                GovernmentResponseStatus::Requested => [
                    'label' => __('Response requested'),
                    'classes' => 'border-amber-200/80 bg-amber-50/70 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/15 dark:text-amber-300',
                ],
            };
        }

        if (
            $report->report_type === ReportType::FinalReport
            && $report->status === ReportStatus::Published
        ) {
            return [
                'label' => __('Awaiting response'),
                'classes' => 'border-amber-200/80 bg-amber-50/70 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/15 dark:text-amber-300',
            ];
        }

        return [
            'label' => __('No response tracked'),
            'classes' => 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
        ];
    }

    public function awaitingGovernmentResponseCount(PlsReview $review): int
    {
        return $review->reports
            ->filter(function (Report $report): bool {
                return $report->report_type === ReportType::FinalReport
                    && $report->status === ReportStatus::Published
                    && $report->governmentResponses->isEmpty();
            })
            ->count();
    }

    public function overdueGovernmentResponseCount(PlsReview $review): int
    {
        return $review->governmentResponses
            ->filter(
                fn (GovernmentResponse $response): bool => $response->response_status === GovernmentResponseStatus::Overdue,
            )
            ->count();
    }

    public function reportWorkflowFocus(PlsReview $review): ?array
    {
        $currentStep = $review->steps->firstWhere('step_number', $review->current_step_number);

        if ($currentStep === null) {
            return null;
        }

        return match ($currentStep->step_key) {
            'draft_report' => [
                'label' => __('Current focus'),
                'title' => __('Draft the committee report'),
                'summary' => __('Shape the evidence base into a report record, connect the working document, and confirm the publication path.'),
            ],
            'dissemination' => [
                'label' => __('Current focus'),
                'title' => __('Prepare the report for publication'),
                'summary' => __('Confirm published status, attach the public-facing document, and make final outputs easy to trace.'),
            ],
            'government_response' => [
                'label' => __('Current focus'),
                'title' => __('Track the government response'),
                'summary' => __('Record whether a response has been requested, received, or has gone overdue against the final report.'),
            ],
            'follow_up' => [
                'label' => __('Current focus'),
                'title' => __('Monitor follow-up after publication'),
                'summary' => __('Use the response history to keep sight of commitments, delays, and documents that support implementation follow-up.'),
            ],
            default => null,
        };
    }

    /**
     * @return array{title: string, summary: string, tab: string, action: string}|null
     */
    public function workspaceGuidance(PlsReview $review): ?array
    {
        $currentStep = $review->steps->firstWhere('step_number', $review->current_step_number);

        if ($currentStep === null) {
            return null;
        }

        return match ($currentStep->step_key) {
            'define_scope' => [
                'title' => __('Define the review scope'),
                'summary' => __('Start by linking the legislation under review and adding the first working papers or briefing documents for the inquiry team.'),
                'tab' => __('Legislation and documents'),
                'action' => __('Link the governing law and upload the committee brief, bill text, or background pack.'),
            ],
            'background_data_plan' => [
                'title' => __('Build the evidence base'),
                'summary' => __('Use the documents area to collect background papers, implementation records, and supporting material before consultations begin.'),
                'tab' => __('Documents'),
                'action' => __('Upload implementation reports, audits, and committee research notes.'),
            ],
            'stakeholder_plan' => [
                'title' => __('Map the people and institutions to involve'),
                'summary' => __('The workspace is ready for stakeholder records and the documents that explain why each voice matters to the review.'),
                'tab' => __('Stakeholders'),
                'action' => __('Add priority stakeholders and capture any supporting briefing documents.'),
            ],
            'implementation_review' => [
                'title' => __('Assess implementation delivery'),
                'summary' => __('Keep agencies, supporting documents, and early findings in sync while the committee examines how the law is working in practice.'),
                'tab' => __('Stakeholders and analysis'),
                'action' => __('Record implementing agencies, then capture the first findings that emerge from implementation evidence.'),
            ],
            'consultations' => [
                'title' => __('Run consultation and evidence intake'),
                'summary' => __('This is the point to log hearings, submissions, and the documents that came in through consultation activity.'),
                'tab' => __('Consultations'),
                'action' => __('Add consultation events and log written submissions as they arrive.'),
            ],
            'analysis' => [
                'title' => __('Turn evidence into conclusions'),
                'summary' => __('The analysis area should now capture the strongest findings and the recommendations tied to them.'),
                'tab' => __('Analysis'),
                'action' => __('Draft findings first, then attach recommendations to the relevant finding.'),
            ],
            'draft_report', 'dissemination' => [
                'title' => __('Prepare the report record'),
                'summary' => __('Reports and linked publication documents should now become the source of truth for what the committee is releasing.'),
                'tab' => __('Reports'),
                'action' => __('Create the report record, link the published file, and keep status up to date.'),
            ],
            'government_response', 'follow_up', 'evaluation' => [
                'title' => __('Track what happens after publication'),
                'summary' => __('Focus on the reports tab so government responses, linked documents, and follow-up signals stay attached to the final report.'),
                'tab' => __('Reports'),
                'action' => __('Keep the report record current and log any response request, reply, or overdue follow-up.'),
            ],
            default => null,
        };
    }

    private function loadReview(): PlsReview
    {
        return $this->review->load([
            'committee.legislature.jurisdiction.country',
            'steps',
            'legislation',
            'legislationObjectives',
            'documents',
            'evidenceItems',
            'stakeholders.submissions.document',
            'implementingAgencies',
            'consultations.document',
            'submissions.stakeholder',
            'submissions.document',
            'findings',
            'recommendations',
            'reports.document',
            'reports.governmentResponses.document',
            'governmentResponses.report',
            'governmentResponses.document',
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Legislation>
     */
    private function attachableLegislation(PlsReview $review): \Illuminate\Database\Eloquent\Collection
    {
        return Legislation::query()
            ->where('jurisdiction_id', $review->jurisdiction_id)
            ->whereNotIn('id', $review->legislation->modelKeys())
            ->orderBy('title')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Stakeholder>
     */
    private function filteredStakeholders(PlsReview $review): EloquentCollection
    {
        if ($this->stakeholderTypeFilter === 'all') {
            return $review->stakeholders;
        }

        return $review->stakeholders->filter(
            fn (Stakeholder $stakeholder): bool => $stakeholder->stakeholder_type->value === $this->stakeholderTypeFilter,
        )->values();
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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

    private function resetLegislationForm(): void
    {
        $this->reset([
            'attachLegislationId',
            'newLegislationTitle',
            'newLegislationShortTitle',
            'newLegislationDateEnacted',
            'newLegislationSummary',
        ]);

        $this->attachLegislationRelationshipType = ReviewLegislationRelationshipType::Related->value;
        $this->newLegislationType = LegislationType::Act->value;
        $this->newLegislationRelationshipType = ReviewLegislationRelationshipType::Primary->value;

        $this->resetValidation([
            'attachLegislationId',
            'newLegislationTitle',
            'newLegislationShortTitle',
            'newLegislationType',
            'newLegislationDateEnacted',
            'newLegislationSummary',
            'newLegislationRelationshipType',
        ]);
    }

    private function resetDocumentForm(): void
    {
        $this->reset([
            'documentEditingId',
            'documentTitle',
            'documentStoragePath',
            'documentFileSize',
            'documentSummary',
            'documentUpload',
        ]);

        $this->documentType = DocumentType::CommitteeReport->value;
        $this->documentMimeType = 'application/pdf';

        $this->resetValidation([
            'documentTitle',
            'documentType',
            'documentStoragePath',
            'documentUpload',
            'documentMimeType',
            'documentFileSize',
            'documentSummary',
        ]);
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

    private function resetStakeholderForm(): void
    {
        $this->reset([
            'stakeholderName',
            'stakeholderOrganization',
            'stakeholderEmail',
            'stakeholderPhone',
        ]);

        $this->stakeholderType = StakeholderType::GovernmentAgency->value;

        $this->resetValidation([
            'stakeholderName',
            'stakeholderType',
            'stakeholderOrganization',
            'stakeholderEmail',
            'stakeholderPhone',
        ]);
    }

    private function resetImplementingAgencyForm(): void
    {
        $this->reset([
            'implementingAgencyName',
        ]);

        $this->implementingAgencyType = ImplementingAgencyType::Agency->value;

        $this->resetValidation([
            'implementingAgencyName',
            'implementingAgencyType',
        ]);
    }

    private function resetFindingForm(): void
    {
        $this->reset([
            'findingEditingId',
            'findingTitle',
            'findingSummary',
            'findingDetail',
        ]);

        $this->findingType = FindingType::ImplementationGap->value;

        $this->resetValidation([
            'findingTitle',
            'findingType',
            'findingSummary',
            'findingDetail',
            'findingEditingId',
        ]);
    }

    private function resetRecommendationForm(): void
    {
        $this->reset([
            'recommendationEditingId',
            'recommendationFindingId',
            'recommendationTitle',
            'recommendationDescription',
        ]);

        $this->recommendationType = RecommendationType::ImproveImplementation->value;

        $this->resetValidation([
            'recommendationFindingId',
            'recommendationTitle',
            'recommendationDescription',
            'recommendationType',
            'recommendationEditingId',
        ]);
    }

    private function resetReportForm(): void
    {
        $this->reset([
            'reportEditingId',
            'reportTitle',
            'reportDocumentId',
            'reportPublishedAt',
        ]);

        $this->reportType = ReportType::DraftReport->value;
        $this->reportStatus = ReportStatus::Draft->value;

        $this->resetValidation([
            'reportTitle',
            'reportType',
            'reportStatus',
            'reportDocumentId',
            'reportPublishedAt',
            'reportEditingId',
        ]);
    }

    private function resetGovernmentResponseForm(): void
    {
        $this->reset([
            'governmentResponseReportId',
            'governmentResponseDocumentId',
            'governmentResponseReceivedAt',
            'governmentResponseSummary',
        ]);

        $this->governmentResponseStatus = GovernmentResponseStatus::Requested->value;

        $this->resetValidation([
            'governmentResponseReportId',
            'governmentResponseDocumentId',
            'governmentResponseStatus',
            'governmentResponseReceivedAt',
            'governmentResponseSummary',
        ]);
    }
}
