<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use App\Domain\Reviews\Support\PlsReviewStepGuidance;
use App\Support\Toast;
use Illuminate\Contracts\View\View;

class WorkflowPage extends Workspace
{
    protected string $workspace = 'workflow';

    public string $reviewTitle = '';

    public string $reviewDescription = '';

    public string $reviewStartDate = '';

    public bool $showEditReviewModal = false;

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.workflow-page', [
            'currentAction' => $this->currentAction($review),
            'recentUploads' => $this->recentUploads($review),
            'workspaceRecord' => $this->workspaceRecord($review),
        ], $review);
    }

    public function prepareReviewEdit(): void
    {
        $this->authorize('update', $this->review);

        $review = $this->loadReview();

        $this->reviewTitle = $review->title;
        $this->reviewDescription = (string) ($review->description ?? '');
        $this->reviewStartDate = $review->start_date?->format('Y-m-d') ?? '';
        $this->resetValidation();
        $this->showEditReviewModal = true;
    }

    public function saveReviewDetails(): void
    {
        $this->authorize('update', $this->review);

        $validated = $this->validate([
            'reviewTitle' => ['required', 'string', 'min:5', 'max:255'],
            'reviewDescription' => ['nullable', 'string', 'max:5000'],
            'reviewStartDate' => ['nullable', 'date'],
        ], [], [
            'reviewTitle' => __('review title'),
            'reviewDescription' => __('review description'),
            'reviewStartDate' => __('start date'),
        ]);

        $this->review->update([
            'title' => trim($validated['reviewTitle']),
            'description' => $this->blankToNull($validated['reviewDescription'] ?? ''),
            'start_date' => $this->blankToNull($validated['reviewStartDate'] ?? ''),
        ]);

        $this->review = $this->review->fresh();
        $this->showEditReviewModal = false;

        $this->dispatch('review-workspace-updated')->to(AssistantSidebar::class);
        $this->dispatchAppToast(Toast::success(
            __('Review details updated'),
            __('The review overview now reflects the latest details.'),
        ));
    }

    public function stepContext(PlsReviewStep $step): string
    {
        return app(PlsReviewStepGuidance::class)->contextForStep($step);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function stepMetricCards(PlsReview $review, PlsReviewStep $step): array
    {
        $documentCount = $this->documentCount($review);

        return match ($step->step_key) {
            'define_scope' => [
                ['label' => __('Legislation linked'), 'value' => (string) $review->legislation->count()],
                ['label' => __('Objectives captured'), 'value' => (string) $review->legislationObjectives->count()],
                ['label' => __('Working documents'), 'value' => (string) $documentCount],
            ],
            'background_data_plan', 'implementation_review' => [
                ['label' => __('Evidence'), 'value' => (string) $documentCount],
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
                ['label' => __('Final documents'), 'value' => (string) $documentCount],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
            'government_response', 'follow_up', 'evaluation' => [
                ['label' => __('Reports'), 'value' => (string) $review->reports->count()],
                ['label' => __('Responses'), 'value' => (string) $review->governmentResponses->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
            default => [
                ['label' => __('Evidence'), 'value' => (string) $documentCount],
                ['label' => __('Findings'), 'value' => (string) $review->findings->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
        };
    }

    private function loadReview(): PlsReview
    {
        return $this->review->load([
            'owner',
            'reviewGroup.legislature.jurisdiction.country',
            'legislature.jurisdiction.country',
            'memberships.user',
            'memberships.invitedBy',
            'steps',
            'legislation',
            'legislationObjectives',
            'documents',
            'evidenceItems',
            'stakeholders',
            'implementingAgencies',
            'consultations',
            'submissions',
            'findings',
            'recommendations',
            'reports',
            'governmentResponses',
        ]);
    }

    private function documentCount(PlsReview $review): int
    {
        return $review->documents
            ->reject(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)
            ->count();
    }

    /**
     * @return array{action: string, button: string, route: string, summary: string, title: string}
     */
    private function currentAction(PlsReview $review): array
    {
        $step = $review->currentStep();
        $guidance = app(PlsReviewStepGuidance::class)->guidanceForStep($step);
        $routeName = match ($step?->step_key) {
            'define_scope' => 'pls.reviews.legislation',
            'background_data_plan' => 'pls.reviews.documents',
            'stakeholder_plan', 'implementation_review' => 'pls.reviews.stakeholders',
            'consultations' => 'pls.reviews.consultations',
            'analysis' => 'pls.reviews.analysis',
            default => 'pls.reviews.reports',
        };

        return [
            'action' => $guidance['action'] ?? __('Review the current materials and choose the next practical task for the inquiry team.'),
            'button' => match ($routeName) {
                'pls.reviews.legislation' => __('Open legislation'),
                'pls.reviews.documents' => __('Open evidence'),
                'pls.reviews.stakeholders' => __('Open stakeholders'),
                'pls.reviews.consultations' => __('Open consultations'),
                'pls.reviews.analysis' => __('Open analysis'),
                default => __('Open reports'),
            },
            'route' => route($routeName, ['review' => $review]),
            'summary' => $guidance['summary'] ?? ($step === null
                ? __('Review the current materials and choose the next practical task for the inquiry team.')
                : $this->stepContext($step)),
            'title' => $guidance['title'] ?? $review->currentStepTitle(),
        ];
    }

    /**
     * @return list<array{action: string, description: string, label: string, route: string}>
     */
    private function missingItems(PlsReview $review): array
    {
        $items = [];
        $evidenceCount = $this->documentCount($review);

        if (blank($review->description)) {
            $items[] = [
                'action' => __('Add details'),
                'description' => __('Record the purpose and scope of this inquiry.'),
                'label' => __('Review brief'),
                'route' => '#review-details',
            ];
        }

        if ($review->legislation->isEmpty()) {
            $items[] = [
                'action' => __('Add legislation'),
                'description' => __('Link the primary or secondary legislation under review.'),
                'label' => __('Legislation'),
                'route' => route('pls.reviews.legislation', ['review' => $review]),
            ];
        }

        if ($evidenceCount === 0) {
            $items[] = [
                'action' => __('Add evidence'),
                'description' => __('Upload background papers, implementation records, or other evidence.'),
                'label' => __('Evidence'),
                'route' => route('pls.reviews.documents', ['review' => $review]),
            ];
        }

        if ($review->stakeholders->isEmpty() && $review->implementingAgencies->isEmpty()) {
            $items[] = [
                'action' => __('Add people and institutions'),
                'description' => __('Map the stakeholders and implementing agencies who should inform the review.'),
                'label' => __('Stakeholders'),
                'route' => route('pls.reviews.stakeholders', ['review' => $review]),
            ];
        }

        if ($review->consultations->isEmpty()) {
            $items[] = [
                'action' => __('Plan consultation'),
                'description' => __('Log hearings, calls for evidence, or other consultation activity when ready.'),
                'label' => __('Consultations'),
                'route' => route('pls.reviews.consultations', ['review' => $review]),
            ];
        }

        if ($review->findings->isEmpty()) {
            $items[] = [
                'action' => __('Add findings'),
                'description' => __('Capture emerging findings once the evidence base supports them.'),
                'label' => __('Analysis'),
                'route' => route('pls.reviews.analysis', ['review' => $review]),
            ];
        }

        if ($review->reports->isEmpty()) {
            $items[] = [
                'action' => __('Create report record'),
                'description' => __('Use reports to track drafting, publication, and government responses.'),
                'label' => __('Reports'),
                'route' => route('pls.reviews.reports', ['review' => $review]),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{detail: string, label: string, route: string, value: string}>
     */
    private function recordedWork(PlsReview $review): array
    {
        $evidenceCount = $this->documentCount($review);

        return [
            [
                'detail' => trans_choice('{0} no linked records|{1} linked record|[2,*] linked records', $review->legislation->count(), ['count' => $review->legislation->count()]),
                'label' => __('Legislation'),
                'route' => route('pls.reviews.legislation', ['review' => $review]),
                'value' => (string) $review->legislation->count(),
            ],
            [
                'detail' => trans_choice('{0} no uploaded files|{1} uploaded file|[2,*] uploaded files', $evidenceCount, ['count' => $evidenceCount]),
                'label' => __('Evidence'),
                'route' => route('pls.reviews.documents', ['review' => $review]),
                'value' => (string) $evidenceCount,
            ],
            [
                'detail' => trans_choice('{0} no people or agencies|{1} person or agency|[2,*] people or agencies', $review->stakeholders->count() + $review->implementingAgencies->count(), ['count' => $review->stakeholders->count() + $review->implementingAgencies->count()]),
                'label' => __('People and institutions'),
                'route' => route('pls.reviews.stakeholders', ['review' => $review]),
                'value' => (string) ($review->stakeholders->count() + $review->implementingAgencies->count()),
            ],
            [
                'detail' => trans_choice('{0} no consultations or submissions|{1} consultation or submission|[2,*] consultations or submissions', $review->consultations->count() + $review->submissions->count(), ['count' => $review->consultations->count() + $review->submissions->count()]),
                'label' => __('Consultations'),
                'route' => route('pls.reviews.consultations', ['review' => $review]),
                'value' => (string) ($review->consultations->count() + $review->submissions->count()),
            ],
            [
                'detail' => trans_choice('{0} no findings or recommendations|{1} finding or recommendation|[2,*] findings or recommendations', $review->findings->count() + $review->recommendations->count(), ['count' => $review->findings->count() + $review->recommendations->count()]),
                'label' => __('Analysis'),
                'route' => route('pls.reviews.analysis', ['review' => $review]),
                'value' => (string) ($review->findings->count() + $review->recommendations->count()),
            ],
            [
                'detail' => trans_choice('{0} no report records|{1} report record|[2,*] report records', $review->reports->count(), ['count' => $review->reports->count()]),
                'label' => __('Reports'),
                'route' => route('pls.reviews.reports', ['review' => $review]),
                'value' => (string) $review->reports->count(),
            ],
        ];
    }

    /**
     * @return list<array{action: string, detail: string, label: string, route: string, status: string, status_color: string, value: string}>
     */
    private function workspaceRecord(PlsReview $review): array
    {
        $gaps = collect($this->missingItems($review))->keyBy('label');

        return collect($this->recordedWork($review))
            ->map(function (array $item) use ($gaps): array {
                $label = $item['label'] === __('People and institutions') ? __('Stakeholders') : $item['label'];
                $gap = $gaps->get($label);
                $count = (int) $item['value'];

                if ($count === 0) {
                    return [
                        ...$item,
                        'action' => $gap['action'] ?? __('Open section'),
                        'detail' => $gap['description'] ?? $item['detail'],
                        'label' => $label,
                        'route' => $gap['route'] ?? $item['route'],
                        'status' => __('Not started'),
                        'status_color' => 'zinc',
                    ];
                }

                return [
                    ...$item,
                    'action' => __('Open section'),
                    'detail' => $gap['description'] ?? $item['detail'],
                    'label' => $label,
                    'route' => $gap['route'] ?? $item['route'],
                    'status' => $gap === null ? __('Recorded') : __('In progress'),
                    'status_color' => $gap === null ? 'emerald' : 'violet',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: string, label: string, route: string, status: string, title: string}>
     */
    private function recentUploads(PlsReview $review): array
    {
        return $review->documents
            ->sortByDesc('updated_at')
            ->take(5)
            ->map(function (Document $document) use ($review): array {
                $isLegislation = $document->document_type === DocumentType::LegislationText;
                $analysisStatus = data_get(
                    $document->metadata,
                    $isLegislation ? 'legislation_analysis.status' : 'document_analysis.status',
                );

                return [
                    'date' => $document->updated_at?->format('j M Y') ?? '',
                    'label' => $isLegislation ? __('Legislation') : $this->documentTypeLabel($document->document_type),
                    'route' => route($isLegislation ? 'pls.reviews.legislation' : 'pls.reviews.documents', ['review' => $review]),
                    'status' => $this->documentStatusLabel(is_string($analysisStatus) ? $analysisStatus : null),
                    'title' => $document->title,
                ];
            })
            ->values()
            ->all();
    }

    private function documentTypeLabel(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::GroupReport => __('Group report'),
            default => str($type->value)->headline()->toString(),
        };
    }

    private function documentStatusLabel(?string $status): string
    {
        return match ($status) {
            'processing', 'extracting_text', 'pending' => __('Processing'),
            'needs_review' => __('Ready to review'),
            'failed' => __('Needs attention'),
            default => __('Saved'),
        };
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
