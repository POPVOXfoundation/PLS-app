<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reporting\Actions\StoreGovernmentResponse;
use App\Domain\Reporting\Actions\StoreReport;
use App\Domain\Reporting\Actions\UpdateReport;
use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class ReportsPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'reports';

    public bool $showAddReportModal = false;

    public bool $showEditReportModal = false;

    public bool $showAddGovernmentResponseModal = false;

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
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();
        $publishedFinalReports = $this->publishedFinalReports($review);
        $awaitingResponseReports = $this->awaitingResponseReports($publishedFinalReports);
        $selectedGovernmentResponseReport = $this->selectedReport($review, $this->governmentResponseReportId);

        return $this->renderWorkspaceView('livewire.pls.reviews.reports-page', [
            'review' => $review,
            'reportTypes' => ReportType::cases(),
            'reportStatuses' => ReportStatus::cases(),
            'governmentResponseStatuses' => GovernmentResponseStatus::cases(),
            'reportWorkflowFocus' => $this->reportWorkflowFocus($review),
            'publishedFinalReports' => $publishedFinalReports,
            'awaitingResponseReports' => $awaitingResponseReports,
            'draftRecommendations' => $review->recommendations->take(3),
            'publishedReportCount' => $review->reports
                ->where('status', ReportStatus::Published)
                ->count(),
            'receivedResponseCount' => $review->governmentResponses
                ->where('response_status', GovernmentResponseStatus::Received)
                ->count(),
            'preferredReportDocuments' => $this->preferredReportDocuments($review),
            'otherReportDocuments' => $this->otherReportDocuments($review),
            'preferredResponseDocuments' => $this->preferredResponseDocuments($review),
            'otherResponseDocuments' => $this->otherResponseDocuments($review),
            'selectedReportDocument' => $this->selectedDocument($review, $this->reportDocumentId),
            'selectedGovernmentResponseReport' => $selectedGovernmentResponseReport,
            'selectedGovernmentResponseLatest' => $selectedGovernmentResponseReport === null
                ? null
                : $this->latestGovernmentResponseForReport($selectedGovernmentResponseReport),
            'selectedGovernmentResponseDocument' => $this->selectedDocument($review, $this->governmentResponseDocumentId),
        ], $review);
    }

    public function prepareReportCreate(?string $reportType = null, ?string $reportStatus = null): void
    {
        $this->authorizeReviewMutation();

        $this->resetReportForm();
        $this->showAddReportModal = true;

        if (($resolvedReportType = ReportType::tryFrom((string) $reportType)) !== null) {
            $this->reportType = $resolvedReportType->value;
        }

        if (($resolvedReportStatus = ReportStatus::tryFrom((string) $reportStatus)) !== null) {
            $this->reportStatus = $resolvedReportStatus->value;
        }

        if (
            $this->reportStatus === ReportStatus::Published->value
            && $this->reportPublishedAt === ''
        ) {
            $this->reportPublishedAt = now()->toDateString();
        }
    }

    public function updatedReportStatus(string $reportStatus): void
    {
        if (
            $reportStatus === ReportStatus::Published->value
            && $this->reportPublishedAt === ''
        ) {
            $this->reportPublishedAt = now()->toDateString();
        }
    }

    public function storeReport(StoreReport $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $status = $this->blankToNull($this->reportPublishedAt) !== null
                ? ReportStatus::Published->value
                : ReportStatus::Draft->value;

            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->reportTitle,
                'report_type' => $this->reportType,
                'status' => $status,
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
        $this->showAddReportModal = false;

        $this->dispatchWorkspaceToast(Toast::success(
            __('Report added'),
            __('Report record added to the review.'),
        ));
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

        $this->showEditReportModal = true;
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
        $this->showEditReportModal = false;

        $this->dispatchWorkspaceToast(Toast::success(
            __('Report updated'),
            __('Report updated.'),
        ));
    }

    public function confirmDeletion(string $type, int $id): void
    {
        $this->authorizeReviewMutation();

        match ($type) {
            'report' => $this->performReportDeletion($id),
            default => null,
        };
    }

    public function prepareGovernmentResponseCreate(?int $reportId = null, ?string $responseStatus = null): void
    {
        $this->authorizeReviewMutation();

        $this->resetGovernmentResponseForm();
        $this->showAddGovernmentResponseModal = true;

        $preferredReportId = $reportId;

        if (
            $preferredReportId === null
            || ! $this->review->reports()->whereKey($preferredReportId)->exists()
        ) {
            $preferredReportId = $this->preferredGovernmentResponseReportId();
        }

        if ($preferredReportId !== null) {
            $this->governmentResponseReportId = (string) $preferredReportId;
        }

        if (($resolvedResponseStatus = GovernmentResponseStatus::tryFrom((string) $responseStatus)) !== null) {
            $this->governmentResponseStatus = $resolvedResponseStatus->value;
        }

        if (
            $this->governmentResponseStatus === GovernmentResponseStatus::Received->value
            && $this->governmentResponseReceivedAt === ''
        ) {
            $this->governmentResponseReceivedAt = now()->toDateString();
        }
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
        $this->showAddGovernmentResponseModal = false;

        $this->dispatchWorkspaceToast(Toast::success(
            __('Response recorded'),
            __('Government response recorded for this review.'),
        ));
    }

    public function updatedGovernmentResponseStatus(string $responseStatus): void
    {
        if (
            $responseStatus === GovernmentResponseStatus::Received->value
            && $this->governmentResponseReceivedAt === ''
        ) {
            $this->governmentResponseReceivedAt = now()->toDateString();
        }
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

    /**
     * @return array{label: string, classes: string, color: string}
     */
    public function reportResponseIndicator(Report $report): array
    {
        $response = $this->latestGovernmentResponseForReport($report);

        if ($response !== null) {
            return match ($response->response_status) {
                GovernmentResponseStatus::Received => [
                    'label' => __('Response received'),
                    'classes' => 'border-emerald-200/80 bg-emerald-50/80 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-300',
                    'color' => 'emerald',
                ],
                GovernmentResponseStatus::Overdue => [
                    'label' => __('Response overdue'),
                    'classes' => 'border-rose-200/80 bg-rose-50/80 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-300',
                    'color' => 'rose',
                ],
                GovernmentResponseStatus::Requested => [
                    'label' => __('Response requested'),
                    'classes' => 'border-amber-200/80 bg-amber-50/70 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/15 dark:text-amber-300',
                    'color' => 'amber',
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
                'color' => 'amber',
            ];
        }

        return [
            'label' => __('No response tracked'),
            'classes' => 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
            'color' => 'zinc',
        ];
    }

    public function awaitingGovernmentResponseCount(PlsReview $review): int
    {
        return $this->awaitingResponseReports($this->publishedFinalReports($review))->count();
    }

    public function overdueGovernmentResponseCount(PlsReview $review): int
    {
        return $review->governmentResponses
            ->filter(
                fn (GovernmentResponse $response): bool => $response->response_status === GovernmentResponseStatus::Overdue,
            )
            ->count();
    }

    /**
     * @return array{label: string, title: string, summary: string}|null
     */
    public function reportWorkflowFocus(PlsReview $review): ?array
    {
        $currentStep = $review->steps->firstWhere('step_number', $review->current_step_number);

        if ($currentStep === null) {
            return null;
        }

        return match ($currentStep->step_key) {
            'draft_report' => [
                'label' => __('Current focus'),
                'title' => __('Draft the review report'),
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

    public function documentTypeLabel(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::GroupReport => __('Group report'),
            default => str($type->value)->headline()->toString(),
        };
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

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'steps',
                'documents',
                'findings',
                'recommendations.finding',
                'reports.document',
                'reports.governmentResponses.document',
                'governmentResponses.report',
                'governmentResponses.document',
            ])
            ->findOrFail($this->review->getKey());
    }

    private function performReportDeletion(int $reportId): void
    {
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
            $this->showEditReportModal = false;
        }

        $this->dispatchWorkspaceToast(Toast::success(
            __('Report removed'),
            __('Report removed from the review.'),
        ));
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function preferredGovernmentResponseReportId(): ?int
    {
        /** @var ?Report $awaitingReport */
        $awaitingReport = $this->review->reports
            ->first(function (Report $report): bool {
                return $report->report_type === ReportType::FinalReport
                    && $report->status === ReportStatus::Published
                    && $report->governmentResponses->isEmpty();
            });

        if ($awaitingReport !== null) {
            return $awaitingReport->id;
        }

        /** @var ?Report $publishedFinalReport */
        $publishedFinalReport = $this->review->reports
            ->first(function (Report $report): bool {
                return $report->report_type === ReportType::FinalReport
                    && $report->status === ReportStatus::Published;
            });

        if ($publishedFinalReport !== null) {
            return $publishedFinalReport->id;
        }

        return $this->review->reports->first()?->id;
    }

    /**
     * @return EloquentCollection<int, Report>
     */
    private function publishedFinalReports(PlsReview $review): EloquentCollection
    {
        return $review->reports->filter(
            fn (Report $report): bool => $report->report_type === ReportType::FinalReport
                && $report->status === ReportStatus::Published,
        );
    }

    /**
     * @param  EloquentCollection<int, Report>  $publishedFinalReports
     * @return EloquentCollection<int, Report>
     */
    private function awaitingResponseReports(EloquentCollection $publishedFinalReports): EloquentCollection
    {
        return $publishedFinalReports->filter(
            fn (Report $report): bool => $report->governmentResponses->isEmpty(),
        );
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    private function preferredReportDocuments(PlsReview $review): EloquentCollection
    {
        return $review->documents->filter(
            fn (Document $document): bool => in_array($document->document_type, $this->reportDocumentTypes(), true),
        );
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    private function otherReportDocuments(PlsReview $review): EloquentCollection
    {
        return $review->documents->reject(
            fn (Document $document): bool => in_array($document->document_type, $this->reportDocumentTypes(), true)
                || $document->document_type === DocumentType::LegislationText,
        );
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    private function preferredResponseDocuments(PlsReview $review): EloquentCollection
    {
        return $review->documents->filter(
            fn (Document $document): bool => $document->document_type === DocumentType::GovernmentResponse,
        );
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    private function otherResponseDocuments(PlsReview $review): EloquentCollection
    {
        return $review->documents->reject(
            fn (Document $document): bool => $document->document_type === DocumentType::GovernmentResponse
                || $document->document_type === DocumentType::LegislationText,
        );
    }

    private function selectedDocument(PlsReview $review, string $documentId): ?Document
    {
        if ($documentId === '') {
            return null;
        }

        /** @var ?Document $document */
        $document = $review->documents->firstWhere('id', (int) $documentId);

        return $document;
    }

    private function selectedReport(PlsReview $review, string $reportId): ?Report
    {
        if ($reportId === '') {
            return null;
        }

        /** @var ?Report $report */
        $report = $review->reports->firstWhere('id', (int) $reportId);

        return $report;
    }

    /**
     * @return list<DocumentType>
     */
    private function reportDocumentTypes(): array
    {
        return [
            DocumentType::DraftReport,
            DocumentType::FinalReport,
            DocumentType::GroupReport,
            DocumentType::PolicyReport,
        ];
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
