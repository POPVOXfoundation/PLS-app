<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Actions\PersistReviewDocumentState;
use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
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
use App\Jobs\ProcessReviewDocument;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ReportsPage extends Workspace
{
    use AuthorizesRequests;
    use WithFileUploads;

    private const MAX_UPLOAD_KB = 51200;

    protected string $workspace = 'reports';

    public bool $showAddReportModal = false;

    public bool $showEditReportModal = false;

    public bool $showReportDraftModal = false;

    public bool $showAddGovernmentResponseModal = false;

    public string $reportDraftRequest = '';

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $reportTemplateUploads = [];

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

    public bool $awaitingReportOutline = false;

    public ?string $reportOutlineError = null;

    /**
     * @var array{title: string, sections: list<array{id: string, title: string, purpose: string, material: string, limitations: string}>}|null
     */
    public ?array $reportOutline = null;

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
            'reportPreview' => $this->reportPreview($review),
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
            'reportTemplateDocuments' => $this->reportTemplateDocuments($review),
        ], $review);
    }

    public function updatedReportTemplateUploads(): void
    {
        $this->authorizeReviewMutation();

        if ($this->reportTemplateUploads === []) {
            return;
        }

        $this->validate([
            'reportTemplateUploads' => ['array', 'min:1'],
            'reportTemplateUploads.*' => ['file', 'mimes:pdf,docx,txt,md', 'max:'.self::MAX_UPLOAD_KB],
        ], [
            'reportTemplateUploads.*.max' => __('Choose files that are 50 MB or smaller.'),
            'reportTemplateUploads.*.mimes' => __('Choose PDF, DOCX, TXT, or MD files only.'),
        ]);

        foreach ($this->reportTemplateUploads as $upload) {
            $storedReview = app(StoreReviewDocumentMetadata::class)->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->documentTitleFromUpload($upload),
                'document_type' => DocumentType::DraftReport->value,
                'storage_path' => null,
                'file' => $upload,
                'mime_type' => null,
                'file_size' => null,
                'summary' => __('Institutional PLS framework, report template, or sample report uploaded to guide drafting.'),
                'metadata' => [
                    'disk' => $this->documentStorageDisk(),
                    'original_name' => $upload->getClientOriginalName(),
                    'purpose' => 'report_template',
                ],
            ]);

            $this->review = $storedReview->fresh();
            $document = $this->review->documents()
                ->where('document_type', DocumentType::DraftReport->value)
                ->where('metadata->purpose', 'report_template')
                ->latest('id')
                ->first();

            if (! $document instanceof Document) {
                $this->addError('reportTemplateUploads', __('One of the uploaded templates could not be stored.'));

                continue;
            }

            app(PersistReviewDocumentState::class)->markQueued($document);
            ProcessReviewDocument::dispatch($document->id);
        }

        $this->reportTemplateUploads = [];
        $this->resetValidation(['reportTemplateUploads', 'reportTemplateUploads.*']);
        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(Toast::success(
            __('Template uploaded'),
            __('PLSAssist will read the template and use it as drafting guidance in report assistance.'),
        ));
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

    public function requestReportOutline(): void
    {
        $this->authorize('view', $this->review);

        $this->awaitingReportOutline = true;
        $this->reportOutlineError = null;
        $this->reportOutline = null;

        $this->dispatch('assistant-prompt-requested', prompt: $this->reportOutlinePrompt(), reportOutline: true)
            ->to(AssistantSidebar::class);
    }

    #[On('report-outline-generated')]
    public function receiveReportOutline(string $content): void
    {
        $this->reportOutline = $this->parseReportOutline($content, $this->loadReview());
        $this->awaitingReportOutline = false;
        $this->reportOutlineError = null;
    }

    #[On('report-outline-failed')]
    public function handleReportOutlineFailure(): void
    {
        $this->awaitingReportOutline = false;
        $this->reportOutlineError = __('PLSAssist could not prepare a report outline just now. You can try again.');
    }

    public function dismissReportOutline(): void
    {
        $this->authorize('view', $this->review);

        $this->reportOutline = null;
        $this->reportOutlineError = null;
    }

    public function prepareReportFromOutline(): void
    {
        $this->authorizeReviewMutation();

        if ($this->reportOutline === null) {
            return;
        }

        $this->prepareReportCreate(ReportType::DraftReport->value, ReportStatus::Draft->value);
        $this->reportTitle = $this->reportOutline['title'];
    }

    public function requestFindingsSectionDraft(): void
    {
        $this->authorize('view', $this->review);

        $this->dispatch('assistant-prompt-requested', prompt: $this->findingsSectionPrompt())
            ->to(AssistantSidebar::class);
    }

    public function requestReportCoverageCheck(): void
    {
        $this->authorize('view', $this->review);

        $this->dispatch('assistant-prompt-requested', prompt: $this->reportCoveragePrompt())
            ->to(AssistantSidebar::class);
    }

    public function prepareReportDraft(): void
    {
        $this->resetValidation('reportDraftRequest');
        $this->reportDraftRequest = '';
        $this->showReportDraftModal = true;
    }

    public function developReportDraft(): void
    {
        $this->authorize('view', $this->review);

        $this->validate([
            'reportDraftRequest' => ['required', 'string', 'max:5000'],
        ], [
            'reportDraftRequest.required' => __('Describe the report section or drafting task you need help with.'),
        ]);

        $request = trim($this->reportDraftRequest);
        $this->reportDraftRequest = '';
        $this->showReportDraftModal = false;

        $this->dispatch('assistant-prompt-requested', prompt: $this->customReportDraftPrompt($request))
            ->to(AssistantSidebar::class);
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
                'stakeholders',
                'implementingAgencies',
                'consultations.materials',
                'submissions',
                'findings',
                'recommendations.finding',
                'reports.document',
                'reports.governmentResponses.document',
                'governmentResponses.report',
                'governmentResponses.document',
            ])
            ->findOrFail($this->review->getKey());
    }

    /**
     * @return array{detail: string, sections: list<array{action: string, detail: string, label: string, ready: bool, route: string, title: string}>, title: string}
     */
    private function reportPreview(PlsReview $review): array
    {
        $evidenceCount = $review->documents
            ->reject(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)
            ->count();
        $consultationResultCount = $review->consultations->sum(
            fn ($consultation): int => $consultation->materials->count(),
        );
        $workingReport = $review->reports
            ->first(fn (Report $report): bool => $report->status === ReportStatus::Draft);

        return [
            'detail' => __('This working preview updates as the review record changes. It does not create or publish a report.'),
            'title' => $workingReport?->title ?? __('Working report preview'),
            'sections' => [
                [
                    'action' => __('Open overview'),
                    'detail' => $review->description ?? __('Add the review purpose and scope to begin this section.'),
                    'label' => __('Purpose and scope'),
                    'ready' => filled($review->description),
                    'route' => route('pls.reviews.workflow', ['review' => $review]).'#review-details',
                    'title' => __('Review overview'),
                ],
                [
                    'action' => __('Open evidence'),
                    'detail' => $evidenceCount > 0
                        ? trans_choice('{1} One evidence record is available for report drafting.|[2,*] :count evidence records are available for report drafting.', $evidenceCount, ['count' => $evidenceCount])
                        : __('Add evidence before writing about implementation or impact.'),
                    'label' => __('Evidence and consultation'),
                    'ready' => $evidenceCount > 0,
                    'route' => route('pls.reviews.documents', ['review' => $review]),
                    'title' => $consultationResultCount > 0
                        ? trans_choice(':count consultation result is also available.|:count consultation results are also available.', $consultationResultCount, ['count' => $consultationResultCount])
                        : ($review->submissions->isNotEmpty()
                            ? trans_choice(':count written submission is recorded.|:count written submissions are recorded.', $review->submissions->count(), ['count' => $review->submissions->count()])
                            : __('No consultation results or written submissions are recorded yet.')),
                ],
                [
                    'action' => __('Open analysis'),
                    'detail' => $review->findings->isNotEmpty()
                        ? trans_choice('{1} One human-reviewed finding is ready to structure the report.|[2,*] :count human-reviewed findings are ready to structure the report.', $review->findings->count(), ['count' => $review->findings->count()])
                        : __('Confirm human-reviewed findings before treating this section as report-ready.'),
                    'label' => __('Findings and recommendations'),
                    'ready' => $review->findings->isNotEmpty(),
                    'route' => route('pls.reviews.analysis', ['review' => $review]),
                    'title' => $review->recommendations->isNotEmpty()
                        ? trans_choice(':count recommendation is linked to the findings.|:count recommendations are linked to the findings.', $review->recommendations->count(), ['count' => $review->recommendations->count()])
                        : __('No recommendations are linked to the reviewed findings yet.'),
                ],
                [
                    'action' => __('Manage reports'),
                    'detail' => $workingReport !== null
                        ? __('A draft report record is already being tracked.')
                        : __('Create a report record when the team is ready to track the working draft.'),
                    'label' => __('Publication path'),
                    'ready' => $workingReport !== null || $review->reports->isNotEmpty(),
                    'route' => route('pls.reviews.reports', ['review' => $review]),
                    'title' => $workingReport?->title ?? __('No report record yet'),
                ],
            ],
        ];
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

    private function reportOutlinePrompt(): string
    {
        $templateContext = $this->reportTemplateContext($this->loadReview());

        return "Prepare a provisional PLS report outline from the saved review record. Do not create, update, or publish a report record. {$templateContext} Adapt this standard PLS structure to the evidence available: executive summary; mandate, scope, and scrutiny questions; legislative intent and implementation architecture; methodology and evidence base; implementation and delivery; confirmed findings; recommendations and expected government response; publication and follow-up. Use only material in the review record. Do not invent findings, recommendations, sources, institutions, or citations. For every section, clearly identify what the review team can draw on and what still needs checking. Keep this as a working structure for human review. Return exactly this format:\n\nREPORT OUTLINE:\nReport title: <working title>\nSECTION:\nTitle: <section title>\nPurpose: <what this section should do in a PLS report>\nDraw on: <specific saved findings, recommendations, legislation, evidence, consultations, or template/framework records>\nLimitations: <gaps or checks for the review team>\nEND SECTION\nEND OUTLINE\n\nInclude up to eight sections.";
    }

    /**
     * @return array{title: string, sections: list<array{id: string, title: string, purpose: string, material: string, limitations: string}>}
     */
    private function parseReportOutline(string $content, PlsReview $review): array
    {
        $title = $this->reportOutlineField($content, 'Report title') ?: __('Draft PLS report');
        $sections = preg_split('/(?:^|\n)SECTION:\s*/i', trim($content)) ?: [];
        $outlineSections = [];

        foreach ($sections as $section) {
            $section = trim((string) preg_replace('/(?:\nEND (?:SECTION|OUTLINE)\s*)+$/i', '', $section));

            if ($section === '') {
                continue;
            }

            $sectionTitle = $this->reportOutlineField($section, 'Title');
            $purpose = $this->reportOutlineField($section, 'Purpose');

            if ($sectionTitle === '' || $purpose === '') {
                continue;
            }

            $outlineSections[] = [
                'id' => (string) Str::uuid(),
                'title' => $sectionTitle,
                'purpose' => $purpose,
                'material' => $this->reportOutlineField($section, 'Draw on'),
                'limitations' => $this->reportOutlineField($section, 'Limitations'),
            ];
        }

        if ($outlineSections === []) {
            return $this->bestPracticeReportOutline($review);
        }

        return [
            'title' => $title,
            'sections' => array_slice($outlineSections, 0, 8),
        ];
    }

    /**
     * Provide a useful PLS structure when the assistant response cannot be parsed.
     *
     * @return array{title: string, sections: list<array{id: string, title: string, purpose: string, material: string, limitations: string}>}
     */
    private function bestPracticeReportOutline(PlsReview $review): array
    {
        $legislationCount = $review->documents
            ->filter(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)
            ->count();
        $evidenceCount = $review->documents
            ->reject(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)
            ->count();
        $consultationMaterialCount = $review->consultations->sum(
            fn ($consultation): int => $consultation->materials->count(),
        );
        $findingCount = $review->findings->count();
        $recommendationCount = $review->recommendations->count();
        $templateDocuments = $this->reportTemplateDocuments($review);

        $sections = [
            [
                'title' => __('Executive summary'),
                'purpose' => __('Give a concise, plain-language account of the review scope, the strongest human-reviewed findings, recommendations, and the most important limitations.'),
                'material' => __('Review: :title. :findings :recommendations', [
                    'title' => $review->title,
                    'findings' => trans_choice('{0} No reviewed findings are recorded yet.|{1} One reviewed finding is recorded.|[2,*] :count reviewed findings are recorded.', $findingCount, ['count' => $findingCount]),
                    'recommendations' => trans_choice('{0} No recommendations are recorded yet.|{1} One recommendation is recorded.|[2,*] :count recommendations are recorded.', $recommendationCount, ['count' => $recommendationCount]),
                ]),
                'limitations' => $findingCount > 0
                    ? __('Agree the headline messages and evidence caveats before drafting this section.')
                    : __('Confirm human-reviewed findings before writing headline conclusions.'),
            ],
            [
                'title' => __('Mandate, scope, and scrutiny questions'),
                'purpose' => __('Set out why the review was undertaken, the legislation and objectives under review, the jurisdiction and period covered, and the questions guiding scrutiny.'),
                'material' => filled($review->description)
                    ? trim($review->description."\n\n".$this->templateSummaryForOutline($templateDocuments))
                    : __('The review record does not yet include a written purpose and scope.'),
                'limitations' => filled($review->description)
                    ? __('Check that the scope identifies the intended outcomes and any exclusions from the inquiry.')
                    : __('Add the review purpose, scope, intended outcomes, and scrutiny questions.'),
            ],
            [
                'title' => __('Legislative intent and implementation architecture'),
                'purpose' => __('Explain the relevant provisions, intended outcomes, commencement or review points, delegated instruments, and the bodies expected to implement the legislation.'),
                'material' => trans_choice('{0} No legislation source is saved in this review.|{1} One legislation source is available for this section.|[2,*] :count legislation sources are available for this section.', $legislationCount, ['count' => $legislationCount]),
                'limitations' => $legislationCount > 0
                    ? __('Check the legislative objectives, implementing responsibilities, and any regulations, orders, or guidance that shape delivery.')
                    : __('Add the primary legislation and any relevant regulations or delegated instruments.'),
            ],
            [
                'title' => __('Methodology and evidence base'),
                'purpose' => __('Describe how the review assembled evidence, including documents, written submissions, consultations, and the limits of that evidence base.'),
                'material' => __(':evidence :submissions :consultations', [
                    'evidence' => trans_choice('{0} No evidence records are saved.|{1} One evidence record is saved.|[2,*] :count evidence records are saved.', $evidenceCount, ['count' => $evidenceCount]),
                    'submissions' => trans_choice('{0} No written submissions are saved.|{1} One written submission is saved.|[2,*] :count written submissions are saved.', $review->submissions->count(), ['count' => $review->submissions->count()]),
                    'consultations' => trans_choice('{0} No consultation materials are saved.|{1} One consultation material is saved.|[2,*] :count consultation materials are saved.', $consultationMaterialCount, ['count' => $consultationMaterialCount]),
                ]),
                'limitations' => $templateDocuments->isNotEmpty()
                    ? __('Use the uploaded institutional framework or sample report to align section order, headings, and required methodology notes.')
                    : __('Record the evidence-selection approach, consultation coverage, time period, and material gaps or limitations.'),
            ],
            [
                'title' => __('Implementation and delivery'),
                'purpose' => __('Assess how the legislation has been put into effect, focusing on responsible bodies, resources, regulations, guidance, governance, and operational delivery.'),
                'material' => __(':agencies :stakeholders', [
                    'agencies' => trans_choice('{0} No implementing agencies are recorded.|{1} One implementing agency is recorded.|[2,*] :count implementing agencies are recorded.', $review->implementingAgencies->count(), ['count' => $review->implementingAgencies->count()]),
                    'stakeholders' => trans_choice('{0} No stakeholders are recorded.|{1} One stakeholder is recorded.|[2,*] :count stakeholders are recorded.', $review->stakeholders->count(), ['count' => $review->stakeholders->count()]),
                ]),
                'limitations' => __('Link claims about implementation to the source material, and separate questions of legal design, commencement, resourcing, guidance, and delivery.'),
            ],
            [
                'title' => __('Findings'),
                'purpose' => __('Present the review team’s human-reviewed findings against the scrutiny questions, distinguishing evidence from interpretation and noting uncertainty.'),
                'material' => $this->outlineRecordTitles(
                    $review->findings,
                    __('No human-reviewed findings are saved yet.'),
                ),
                'limitations' => $findingCount > 0
                    ? __('Check that each finding is evidence-grounded and linked to the relevant scrutiny question.')
                    : __('Use the analysis workspace to review and confirm findings before treating this section as ready.'),
            ],
            [
                'title' => __('Recommendations and expected government response'),
                'purpose' => __('Set out the review team’s confirmed recommendations, who is expected to act, and the response or comply-or-explain process where it applies.'),
                'material' => $this->outlineRecordTitles(
                    $review->recommendations,
                    __('No recommendations are saved yet.'),
                ),
                'limitations' => $recommendationCount > 0
                    ? __('Check that each recommendation is specific, linked to a finding, and identifies the responsible body and response expectation.')
                    : __('Confirm recommendations from the reviewed findings before drafting this section.'),
            ],
            [
                'title' => __('Publication, response, and follow-up'),
                'purpose' => __('Record the publication and accessibility plan, the expected government response timetable, and how the committee or inquiry team will monitor implementation after publication.'),
                'material' => $review->reports->isNotEmpty()
                    ? trans_choice('{1} One report record is being tracked.|[2,*] :count report records are being tracked.', $review->reports->count(), ['count' => $review->reports->count()])
                    : __('No report record has been created yet.'),
                'limitations' => __('Confirm the publication route, accessibility needs, response deadline, and follow-up responsibilities before release.'),
            ],
        ];

        return [
            'title' => __('Provisional PLS report: :title', ['title' => $review->title]),
            'sections' => array_map(
                fn (array $section): array => ['id' => (string) Str::uuid(), ...$section],
                $sections,
            ),
        ];
    }

    /**
     * @param  EloquentCollection<int, \Illuminate\Database\Eloquent\Model>  $records
     */
    private function outlineRecordTitles(EloquentCollection $records, string $emptyMessage): string
    {
        if ($records->isEmpty()) {
            return $emptyMessage;
        }

        $titles = $records
            ->take(3)
            ->pluck('title')
            ->filter()
            ->implode('; ');

        return $titles !== ''
            ? $titles
            : trans_choice('{1} One saved record is available.|[2,*] :count saved records are available.', $records->count(), ['count' => $records->count()]);
    }

    private function reportOutlineField(string $content, string $label): string
    {
        $labels = ['Report title', 'SECTION', 'Title', 'Purpose', 'Draw on', 'Limitations', 'END SECTION', 'END OUTLINE'];
        $otherLabels = array_values(array_filter($labels, fn (string $candidate): bool => $candidate !== $label));
        $followingLabels = implode('|', array_map(fn (string $candidate): string => preg_quote($candidate, '/'), $otherLabels));
        $pattern = '/(?:^|\n)'.preg_quote($label, '/').':\s*(.*?)(?=\n(?:'.$followingLabels.'):\s*|\z)/is';

        preg_match($pattern, $content, $matches);

        return isset($matches[1])
            ? trim(preg_replace('/\n{3,}/', "\n\n", trim($matches[1])) ?? '')
            : '';
    }

    private function findingsSectionPrompt(): string
    {
        return 'Draft a report section that presents the confirmed findings and associated recommendations currently recorded in this review. '.$this->reportTemplateContext($this->loadReview()).' Preserve their substance and evidence limitations, distinguish findings from recommendations, and use cautious report language. Mention the source record where it is available, but do not invent citations, add new findings, or present the report as final. End with a short list of points the review team should verify before using the draft.';
    }

    private function reportCoveragePrompt(): string
    {
        return 'Check the current review record for report-drafting readiness. '.$this->reportTemplateContext($this->loadReview()).' Compare the review scope, confirmed findings, recommendations, evidence record, consultations, uploaded templates or frameworks, and report records. Identify the strongest available material, missing sections or evidence, and questions the review team should resolve before publishing. Do not create a report, make new findings, or treat draft material as final.';
    }

    private function customReportDraftPrompt(string $request): string
    {
        return sprintf(
            'The review team needs help with this report drafting task: "%s". %s Use only the current review record, especially the confirmed findings, recommendations, and any uploaded parliamentary template or framework. Provide a clearly labelled draft with any source limitations or unresolved questions. Do not create or update a report record, add new findings, invent citations, or present the output as final publication language.',
            $request,
            $this->reportTemplateContext($this->loadReview()),
        );
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    private function reportTemplateDocuments(PlsReview $review): EloquentCollection
    {
        return $review->documents
            ->filter(fn (Document $document): bool => data_get($document->metadata, 'purpose') === 'report_template')
            ->sortByDesc(fn (Document $document): int => $document->updated_at?->timestamp ?? $document->created_at?->timestamp ?? 0)
            ->values();
    }

    private function reportTemplateContext(PlsReview $review): string
    {
        $templates = $this->reportTemplateDocuments($review);

        if ($templates->isEmpty()) {
            return 'If no institutional PLS template or sample report is uploaded, use the standard PLS structure and flag that the review team may need to adapt it to its parliament’s format.';
        }

        $titles = $templates
            ->take(3)
            ->pluck('title')
            ->filter()
            ->implode('; ');

        return sprintf(
            'The review includes uploaded institutional PLS template/framework material%s. Treat those files as formatting and structure guidance for report drafting. Follow their headings, required sections, tone, and ordering where they are clear, while keeping all substantive claims grounded in the review record.',
            $titles === '' ? '' : ': '.$titles,
        );
    }

    /**
     * @param  EloquentCollection<int, Document>  $templates
     */
    private function templateSummaryForOutline(EloquentCollection $templates): string
    {
        if ($templates->isEmpty()) {
            return '';
        }

        return __('Institutional template/framework uploaded: :titles', [
            'titles' => $templates->take(3)->pluck('title')->filter()->implode('; '),
        ]);
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

    private function documentTitleFromUpload(TemporaryUploadedFile $upload): string
    {
        $baseName = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = preg_replace('/[_-][a-f0-9]{8,}$/i', '', $baseName) ?? $baseName;

        return Str::of($baseName)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->trim()
            ->toString();
    }

    private function documentStorageDisk(): string
    {
        $configuredSourceDisk = trim((string) config('pls_assistant.assistant_sources.source_disk', ''));
        $configuredExtractor = (string) config('pls_assistant.assistant_sources.extractor', 'local');

        if (
            $configuredExtractor === 'textract'
            && (string) config('filesystems.disks.s3.driver', '') === 's3'
        ) {
            return 's3';
        }

        if ($configuredSourceDisk !== '') {
            return $configuredSourceDisk;
        }

        return (string) config('filesystems.default');
    }
}
