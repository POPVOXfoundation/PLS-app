<?php

namespace App\Support\PlsAssistant;

use App\Domain\Analysis\Finding;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Legislation\Legislation;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use App\Domain\Reviews\PlsReviewStep;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReviewAssistantContextBuilder
{
    /**
     * @return array{
     *     intro: string,
     *     playbook: array{
     *         role: string,
     *         objectives: list<string>,
     *         suggested_prompts: list<string>,
     *         rules: list<string>,
     *         guardrails: list<string>,
     *         allowed_capabilities: list<string>
     *     },
     *     playbook_version: string,
     *     system_rules: list<string>,
     *     workspace_key: string,
     *     workspace_label: string,
     *     context: string
     * }
     */
    public function build(PlsReview $review, string $workspaceKey): array
    {
        $workspaceKey = $this->resolveWorkspaceKey($workspaceKey);
        $review = $this->hydrateReview($review);
        $playbook = $this->playbook($workspaceKey);

        return [
            'intro' => $playbook['objectives'][0] ?? '',
            'playbook' => $playbook,
            'playbook_version' => $this->playbookVersion(),
            'system_rules' => config('pls_assistant.system_rules', []),
            'workspace_key' => $workspaceKey,
            'workspace_label' => $this->workspaceLabel($workspaceKey),
            'context' => $this->contextString($review, $workspaceKey),
        ];
    }

    public function hydrateReview(PlsReview $review): PlsReview
    {
        return $review->loadMissing([
            'owner',
            'reviewGroup.legislature.jurisdiction.country',
            'legislature.jurisdiction.country',
            'memberships.user',
            'steps',
            'legislation',
            'legislationObjectives',
            'documents',
            'stakeholders.submissions',
            'implementingAgencies',
            'consultations.document',
            'submissions.stakeholder',
            'findings',
            'recommendations.finding',
            'reports.document',
            'reports.governmentResponses',
            'governmentResponses.report',
        ]);
    }

    public function playbook(string $workspaceKey): array
    {
        $workspaceKey = $this->resolveWorkspaceKey($workspaceKey);

        /** @var array{
         *     role: string,
         *     objectives: list<string>,
         *     suggested_prompts: list<string>,
         *     rules: list<string>,
         *     guardrails: list<string>,
         *     allowed_capabilities: list<string>
         * } $playbook
         */
        $playbook = config("pls_assistant.tabs.{$workspaceKey}");

        return $playbook;
    }

    public function playbookVersion(): string
    {
        return (string) config('pls_assistant.version', 'v1');
    }

    public function workspaceLabel(string $workspaceKey): string
    {
        return match ($this->resolveWorkspaceKey($workspaceKey)) {
            'workflow' => 'Workflow',
            'collaborators' => 'Collaborators',
            'legislation' => 'Legislation',
            'documents' => 'Documents',
            'stakeholders' => 'Stakeholders',
            'consultations' => 'Consultations',
            'analysis' => 'Analysis',
            'reports' => 'Reports',
            default => Str::headline($workspaceKey),
        };
    }

    public function resolveWorkspaceKey(string $workspaceKey): string
    {
        return config()->has("pls_assistant.tabs.{$workspaceKey}")
            ? $workspaceKey
            : 'workflow';
    }

    private function contextString(PlsReview $review, string $workspaceKey): string
    {
        return implode(PHP_EOL.PHP_EOL, array_filter([
            'Playbook version: '.$this->playbookVersion(),
            'Active tab: '.$this->workspaceLabel($workspaceKey),
            'Review summary:'.PHP_EOL.$this->reviewSummary($review),
            'Workflow summary:'.PHP_EOL.$this->workflowSummary($review),
            'Current step guidance:'.PHP_EOL.$this->currentStepGuidance($review),
            'Tab facts:'.PHP_EOL.$this->workspaceFacts($review, $workspaceKey),
        ]));
    }

    private function reviewSummary(PlsReview $review): string
    {
        return implode(PHP_EOL, array_filter([
            'Review title: '.$review->title,
            $review->description ? 'Review description: '.$review->description : null,
            'Review status: '.$review->statusLabel(),
            'Owner: '.($review->owner?->name ?? 'No owner recorded'),
            'Assignment: '.$review->assignmentLabel(),
        ]));
    }

    private function workflowSummary(PlsReview $review): string
    {
        $steps = $review->steps
            ->map(fn (PlsReviewStep $step): string => sprintf(
                '- Step %d: %s [%s]',
                $step->step_number,
                $step->title,
                $step->statusLabel(),
            ))
            ->implode(PHP_EOL);

        return implode(PHP_EOL, array_filter([
            sprintf(
                'Progress: Step %d of %d (%d%%)',
                $review->current_step_number,
                max($review->steps->count(), 1),
                $review->progressPercentage(),
            ),
            'Current step: '.$review->currentStepTitle(),
            $review->currentStep() ? 'Current step status: '.$review->currentStep()->statusLabel() : null,
            $review->currentStep() ? 'Current step purpose: '.$this->stepContext($review->currentStep()) : null,
            'Step list:',
            $steps,
        ]));
    }

    private function currentStepGuidance(PlsReview $review): string
    {
        $guidance = $this->workflowGuidance($review);

        if ($guidance === null) {
            return 'No additional step guidance is available from the current review step.';
        }

        return implode(PHP_EOL, [
            'Focus: '.$guidance['title'],
            'Best tab: '.$guidance['tab'],
            'Suggested action: '.$guidance['action'],
            'Why: '.$guidance['summary'],
        ]);
    }

    private function workspaceFacts(PlsReview $review, string $workspaceKey): string
    {
        return match ($workspaceKey) {
            'workflow' => $this->workflowFacts($review),
            'documents' => $this->documentsFacts($review),
            'legislation' => $this->legislationFacts($review),
            'collaborators' => $this->collaboratorFacts($review),
            'stakeholders' => $this->stakeholderFacts($review),
            'consultations' => $this->consultationFacts($review),
            'analysis' => $this->analysisFacts($review),
            'reports' => $this->reportFacts($review),
            default => 'No tab facts are available.',
        };
    }

    private function workflowFacts(PlsReview $review): string
    {
        return implode(PHP_EOL, [
            sprintf(
                'Current record counts: legislation=%d; documents=%d; stakeholders=%d; consultations=%d; submissions=%d; findings=%d; recommendations=%d; reports=%d; government_responses=%d',
                $review->legislation->count(),
                $review->documents->count(),
                $review->stakeholders->count(),
                $review->consultations->count(),
                $review->submissions->count(),
                $review->findings->count(),
                $review->recommendations->count(),
                $review->reports->count(),
                $review->governmentResponses->count(),
            ),
            $review->firstOpenStepAfter($review->current_step_number) !== null
                ? 'Next open step: '.$review->firstOpenStepAfter($review->current_step_number)->title
                : 'Next open step: none recorded',
        ]);
    }

    private function documentsFacts(PlsReview $review): string
    {
        if ($review->documents->isEmpty()) {
            return 'No documents are attached to this review yet.';
        }

        return implode(PHP_EOL, [
            'Documents attached: '.$review->documents->count(),
            $this->formatList($review->documents, fn (Document $document): string => sprintf(
                '- %s [%s]%s',
                $document->title,
                Str::headline($document->document_type->value),
                $document->summary ? ' Summary: '.$document->summary : '',
            )),
        ]);
    }

    private function legislationFacts(PlsReview $review): string
    {
        if ($review->legislation->isEmpty()) {
            return 'No legislation is linked to this review yet.';
        }

        return implode(PHP_EOL, [
            'Linked legislation: '.$review->legislation->count(),
            $this->formatList($review->legislation, fn (Legislation $legislation): string => sprintf(
                '- %s [%s]%s%s',
                $legislation->title,
                Str::headline($legislation->legislation_type->value),
                $legislation->pivot?->relationship_type ? ' Relationship: '.Str::headline((string) $legislation->pivot->relationship_type) : '',
                $legislation->summary ? ' Summary: '.$legislation->summary : '',
            )),
        ]);
    }

    private function collaboratorFacts(PlsReview $review): string
    {
        if ($review->memberships->isEmpty()) {
            return 'No collaborators are attached to this review yet.';
        }

        return implode(PHP_EOL, [
            'Collaborators: '.$review->memberships->count(),
            $this->formatList($review->memberships, fn (PlsReviewMembership $membership): string => sprintf(
                '- %s [%s]',
                $membership->user?->name ?? 'Unknown user',
                Str::headline($membership->role->value),
            )),
        ]);
    }

    private function stakeholderFacts(PlsReview $review): string
    {
        if ($review->stakeholders->isEmpty()) {
            return 'No stakeholders are recorded yet.';
        }

        $missingContacts = $review->stakeholders
            ->filter(fn (Stakeholder $stakeholder): bool => blank($stakeholder->contact_details['email'] ?? null) && blank($stakeholder->contact_details['phone'] ?? null));

        return implode(PHP_EOL, [
            'Stakeholders: '.$review->stakeholders->count(),
            'Implementing agencies: '.$review->implementingAgencies->count(),
            'Stakeholders missing direct contact detail: '.$missingContacts->count(),
            $this->formatList($review->stakeholders, fn (Stakeholder $stakeholder): string => sprintf(
                '- %s [%s]%s',
                $stakeholder->name,
                Str::headline($stakeholder->stakeholder_type->value),
                $stakeholder->submissions->isNotEmpty() ? ' Submissions: '.$stakeholder->submissions->count() : '',
            )),
        ]);
    }

    private function consultationFacts(PlsReview $review): string
    {
        if ($review->consultations->isEmpty() && $review->submissions->isEmpty()) {
            return 'No consultations or submissions are recorded yet.';
        }

        $plannedCount = $review->consultations->filter(fn (Consultation $consultation): bool => $consultation->held_at === null)->count();
        $completedCount = $review->consultations->count() - $plannedCount;

        return implode(PHP_EOL, [
            sprintf(
                'Consultations: total=%d; completed=%d; planned=%d',
                $review->consultations->count(),
                $completedCount,
                $plannedCount,
            ),
            'Submissions: '.$review->submissions->count(),
            $this->formatList($review->consultations, fn (Consultation $consultation): string => sprintf(
                '- %s [%s]%s%s',
                $consultation->title,
                Str::headline($consultation->consultation_type->value),
                $consultation->held_at ? ' Held: '.$consultation->held_at->toDateString() : ' Planned',
                $consultation->summary ? ' Summary: '.$consultation->summary : '',
            )),
            $review->submissions->isEmpty()
                ? 'No submissions are logged yet.'
                : $this->formatList($review->submissions, fn (Submission $submission): string => sprintf(
                    '- Submission from %s%s',
                    $submission->stakeholder?->name ?? 'Unknown stakeholder',
                    $submission->summary ? ': '.$submission->summary : '',
                )),
        ]);
    }

    private function analysisFacts(PlsReview $review): string
    {
        if ($review->findings->isEmpty() && $review->recommendations->isEmpty()) {
            return 'No findings or recommendations are recorded yet.';
        }

        return implode(PHP_EOL, [
            'Findings: '.$review->findings->count(),
            'Recommendations: '.$review->recommendations->count(),
            $review->findings->isEmpty()
                ? 'No findings are recorded yet.'
                : $this->formatList($review->findings, fn (Finding $finding): string => sprintf(
                    '- %s [%s]%s',
                    $finding->title,
                    Str::headline($finding->finding_type->value),
                    $finding->summary ? ' Summary: '.$finding->summary : '',
                )),
            $review->recommendations->isEmpty()
                ? 'No recommendations are recorded yet.'
                : $this->formatList($review->recommendations, fn ($recommendation): string => sprintf(
                    '- %s%s',
                    $recommendation->title,
                    $recommendation->description ? ': '.$recommendation->description : '',
                )),
        ]);
    }

    private function reportFacts(PlsReview $review): string
    {
        if ($review->reports->isEmpty() && $review->governmentResponses->isEmpty()) {
            return 'No reports or government responses are recorded yet.';
        }

        return implode(PHP_EOL, [
            'Reports: '.$review->reports->count(),
            'Government responses: '.$review->governmentResponses->count(),
            $review->reports->isEmpty()
                ? 'No reports are recorded yet.'
                : $this->formatList($review->reports, fn (Report $report): string => sprintf(
                    '- %s [%s | %s]%s',
                    $report->title,
                    Str::headline($report->report_type->value),
                    Str::headline($report->status->value),
                    $report->published_at ? ' Published: '.$report->published_at->toDateString() : '',
                )),
            $review->governmentResponses->isEmpty()
                ? 'No government responses are recorded yet.'
                : $this->formatList($review->governmentResponses, fn (GovernmentResponse $response): string => sprintf(
                    '- %s%s%s',
                    $response->report?->title ?? 'Unlinked report',
                    ' ['.Str::headline($response->response_status->value).']',
                    $response->summary ? ' '.$response->summary : '',
                )),
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private function formatList(Collection $items, callable $formatter, int $limit = 6): string
    {
        return $items
            ->take($limit)
            ->map($formatter)
            ->implode(PHP_EOL);
    }

    private function stepContext(PlsReviewStep $step): string
    {
        return match ($step->step_key) {
            'define_scope' => 'Confirm the legislation under review, the institutional context, and the boundaries of the inquiry.',
            'background_data_plan' => 'Assemble source material, implementation records, and baseline evidence to guide the review.',
            'stakeholder_plan' => 'Map the institutions and external actors that should inform the scrutiny process.',
            'implementation_review' => 'Examine delivery agencies, delegated powers, and operational bottlenecks in implementation.',
            'consultations' => 'Capture written and oral input from the public, experts, and implementing institutions.',
            'analysis' => 'Synthesize evidence into findings and identify the strongest recommendation themes.',
            'draft_report' => 'Translate the inquiry record into a review report with clear conclusions and actions.',
            'dissemination' => 'Track publication readiness, accessibility, and the materials needed for public release.',
            'government_response' => 'Monitor whether the executive has responded and whether commitments are on record.',
            'follow_up' => 'Keep sight of implementation progress after the report phase concludes.',
            'evaluation' => 'Assess whether the review process produced usable lessons, evidence, and institutional value.',
            default => 'Review the current materials attached to this workflow step.',
        };
    }

    /**
     * @return array{title: string, summary: string, tab: string, action: string}|null
     */
    private function workflowGuidance(PlsReview $review): ?array
    {
        $currentStep = $review->steps->firstWhere('step_number', $review->current_step_number);

        if ($currentStep === null) {
            return null;
        }

        return match ($currentStep->step_key) {
            'define_scope' => [
                'title' => 'Define the review scope',
                'summary' => 'Start by linking the legislation under review and adding the first working papers or briefing documents for the inquiry team.',
                'tab' => 'Legislation and documents',
                'action' => 'Link the governing law and upload the initial briefing, bill text, or background pack.',
            ],
            'background_data_plan' => [
                'title' => 'Build the evidence base',
                'summary' => 'Use the documents area to collect background papers, implementation records, and supporting material before consultations begin.',
                'tab' => 'Documents',
                'action' => 'Upload implementation reports, audits, and background research notes.',
            ],
            'stakeholder_plan' => [
                'title' => 'Map the people and institutions to involve',
                'summary' => 'The workspace is ready for stakeholder records and the documents that explain why each voice matters to the review.',
                'tab' => 'Stakeholders',
                'action' => 'Add priority stakeholders and capture any supporting briefing documents.',
            ],
            'implementation_review' => [
                'title' => 'Assess implementation delivery',
                'summary' => 'Keep agencies, supporting documents, and early findings in sync while the review examines how the law is working in practice.',
                'tab' => 'Stakeholders and analysis',
                'action' => 'Record implementing agencies, then capture the first findings that emerge from implementation evidence.',
            ],
            'consultations' => [
                'title' => 'Run consultation and evidence intake',
                'summary' => 'This is the point to log hearings, submissions, and the documents that came in through consultation activity.',
                'tab' => 'Consultations',
                'action' => 'Add consultation events and log written submissions as they arrive.',
            ],
            'analysis' => [
                'title' => 'Turn evidence into conclusions',
                'summary' => 'The analysis area should now capture the strongest findings and the recommendations tied to them.',
                'tab' => 'Analysis',
                'action' => 'Draft findings first, then attach recommendations to the relevant finding.',
            ],
            'draft_report', 'dissemination' => [
                'title' => 'Prepare the report record',
                'summary' => 'Reports and linked publication documents should now become the source of truth for what this inquiry is releasing.',
                'tab' => 'Reports',
                'action' => 'Create the report record, link the published file, and keep status up to date.',
            ],
            'government_response', 'follow_up', 'evaluation' => [
                'title' => 'Track what happens after publication',
                'summary' => 'Focus on the reports tab so government responses, linked documents, and follow-up signals stay attached to the final report.',
                'tab' => 'Reports',
                'action' => 'Keep the report record current and log any response request, reply, or overdue follow-up.',
            ],
            default => null,
        };
    }
}
