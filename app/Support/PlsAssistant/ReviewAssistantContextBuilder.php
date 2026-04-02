<?php

namespace App\Support\PlsAssistant;

use App\Domain\Analysis\Finding;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use App\Domain\Reviews\PlsReviewStep;
use App\Domain\Reviews\Support\PlsReviewStepGuidance;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReviewAssistantContextBuilder
{
    public function __construct(
        protected PlaybookRepository $playbooks,
        protected PlsReviewStepGuidance $stepGuidance,
        protected ReviewAssistantGroundingRepository $grounding,
    ) {}

    /**
     * @return array{
     *     context: string,
     *     intro: string,
     *     playbook: array{
     *         allowed_capabilities: list<string>,
     *         disallowed_capabilities: list<string>,
     *         guardrails: list<string>,
     *         intro: string,
     *         objectives: list<string>,
     *         response_style: list<string>,
     *         role: string,
     *         rules: list<string>,
     *         suggested_prompts: list<string>
     *     },
     *     playbook_version: string,
     *     structured_context: array{
     *         evidence_warnings: list<string>,
     *         grounding: array{
     *             global: list<array{excerpt: string, label: string, source: string}>,
     *             jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *             review: list<array{excerpt: string, label: string, source: string}>
     *         },
     *         known_gaps: list<string>,
     *         record_readiness: list<string>,
     *         review: array{assignment: string, description: string|null, owner: string, status: string, title: string},
     *         tab_facts: list<string>,
     *         workflow: array{
     *             current_step: string,
     *             current_step_status: string|null,
     *             guidance: array{action: string, summary: string, tab: string, title: string}|null,
     *             progress: string,
     *             step_list: list<string>,
     *             step_purpose: string|null
     *         }
     *     },
     *     system_rules: list<string>,
     *     workspace_key: string,
     *     workspace_label: string
     * }
     */
    public function build(PlsReview $review, string $workspaceKey, string $prompt = ''): array
    {
        $workspaceKey = $this->resolveWorkspaceKey($workspaceKey);
        $review = $this->hydrateReview($review);
        $playbook = $this->playbooks->tab($workspaceKey);
        $structuredContext = $this->structuredContext($review, $workspaceKey, $prompt);

        return [
            'intro' => $this->introFor($playbook, $structuredContext),
            'playbook' => $playbook,
            'playbook_version' => $this->playbooks->versionForTab($workspaceKey),
            'structured_context' => $structuredContext,
            'system_rules' => $this->playbooks->systemRules(),
            'workspace_key' => $workspaceKey,
            'workspace_label' => $this->workspaceLabel($workspaceKey),
            'context' => $this->contextString($structuredContext, $workspaceKey, $prompt),
        ];
    }

    public function hydrateReview(PlsReview $review): PlsReview
    {
        return $review->loadMissing([
            'owner',
            'country',
            'reviewGroup.legislature.jurisdiction.country',
            'legislature.jurisdiction.country',
            'jurisdiction.country',
            'memberships.user',
            'steps',
            'legislation',
            'legislationObjectives',
            'documents.chunks',
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

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     suggested_prompts: list<string>
     * }
     */
    public function playbook(string $workspaceKey): array
    {
        return $this->playbooks->tab($workspaceKey);
    }

    public function playbookVersion(string $workspaceKey): string
    {
        return $this->playbooks->versionForTab($workspaceKey);
    }

    public function workspaceLabel(string $workspaceKey): string
    {
        return $this->playbooks->workspaceLabel($workspaceKey);
    }

    public function resolveWorkspaceKey(string $workspaceKey): string
    {
        return $this->playbooks->resolveWorkspaceKey($workspaceKey);
    }

    /**
     * @return array{
     *     evidence_warnings: list<string>,
     *     grounding: array{
     *         global: list<array{excerpt: string, label: string, source: string}>,
     *         jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *         review: list<array{excerpt: string, label: string, source: string}>
     *     },
     *     known_gaps: list<string>,
     *     record_readiness: list<string>,
     *     review: array{assignment: string, description: string|null, owner: string, status: string, title: string},
     *     tab_facts: list<string>,
     *     workflow: array{
     *         current_step: string,
     *         current_step_status: string|null,
     *         guidance: array{action: string, summary: string, tab: string, title: string}|null,
     *         progress: string,
     *         step_list: list<string>,
     *         step_purpose: string|null
     *     }
     * }
     */
    private function structuredContext(PlsReview $review, string $workspaceKey, string $prompt): array
    {
        $currentStep = $review->currentStep();

        return [
            'review' => [
                'title' => $review->title,
                'description' => $review->description,
                'status' => $review->statusLabel(),
                'owner' => $review->owner?->name ?? 'No owner recorded',
                'assignment' => $review->assignmentLabel(),
            ],
            'workflow' => [
                'progress' => sprintf(
                    'Step %d of %d (%d%%)',
                    $review->current_step_number,
                    max($review->steps->count(), 1),
                    $review->progressPercentage(),
                ),
                'current_step' => $review->currentStepTitle(),
                'current_step_status' => $currentStep?->statusLabel(),
                'step_purpose' => $currentStep ? $this->stepGuidance->contextForStep($currentStep) : null,
                'guidance' => $this->stepGuidance->guidanceForStep($currentStep),
                'step_list' => $review->steps
                    ->map(fn (PlsReviewStep $step): string => sprintf(
                        'Step %d: %s [%s]',
                        $step->step_number,
                        $step->title,
                        $step->statusLabel(),
                    ))
                    ->values()
                    ->all(),
            ],
            'tab_facts' => $this->workspaceFacts($review, $workspaceKey),
            'known_gaps' => $this->knownGaps($review),
            'evidence_warnings' => $this->evidenceWarnings($review),
            'record_readiness' => $this->recordReadiness($review),
            'grounding' => $this->grounding->forPrompt($review, $prompt, $workspaceKey),
        ];
    }

    /**
     * @param  array{
     *     evidence_warnings: list<string>,
     *     grounding: array{
     *         global: list<array{excerpt: string, label: string, source: string}>,
     *         jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *         review: list<array{excerpt: string, label: string, source: string}>
     *     },
     *     known_gaps: list<string>,
     *     record_readiness: list<string>,
     *     review: array{assignment: string, description: string|null, owner: string, status: string, title: string},
     *     tab_facts: list<string>,
     *     workflow: array{
     *         current_step: string,
     *         current_step_status: string|null,
     *         guidance: array{action: string, summary: string, tab: string, title: string}|null,
     *         progress: string,
     *         step_list: list<string>,
     *         step_purpose: string|null
     *     }
     * } $structuredContext
     * @param  array{
     *     intro: string
     * } $playbook
     */
    private function introFor(array $playbook, array $structuredContext): string
    {
        $sources = [];

        if ($structuredContext['grounding']['review'] !== []) {
            $sources[] = 'the current review record';
        }

        if ($structuredContext['grounding']['jurisdiction'] !== []) {
            $sources[] = 'jurisdiction guidance';
        }

        if ($structuredContext['grounding']['global'] !== []) {
            $sources[] = 'global reference guidance';
        }

        if ($sources === []) {
            return $playbook['intro'];
        }

        return $playbook['intro'].' I will distinguish between '.implode(', ', $sources).'.';
    }

    /**
     * @param  array{
     *     evidence_warnings: list<string>,
     *     grounding: array{
     *         global: list<array{excerpt: string, label: string, source: string}>,
     *         jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *         review: list<array{excerpt: string, label: string, source: string}>
     *     },
     *     known_gaps: list<string>,
     *     record_readiness: list<string>,
     *     review: array{assignment: string, description: string|null, owner: string, status: string, title: string},
     *     tab_facts: list<string>,
     *     workflow: array{
     *         current_step: string,
     *         current_step_status: string|null,
     *         guidance: array{action: string, summary: string, tab: string, title: string}|null,
     *         progress: string,
     *         step_list: list<string>,
     *         step_purpose: string|null
     *     }
     * } $structuredContext
     */
    private function contextString(array $structuredContext, string $workspaceKey, string $prompt): string
    {
        $workflowGuidance = $structuredContext['workflow']['guidance'];

        return implode(PHP_EOL.PHP_EOL, array_filter([
            'Playbook version: '.$this->playbookVersion($workspaceKey),
            'Active tab: '.$this->workspaceLabel($workspaceKey),
            'Grounding priority: '.$this->groundingPriority($prompt),
            'Review summary:'.PHP_EOL.implode(PHP_EOL, array_filter([
                'Review title: '.$structuredContext['review']['title'],
                $structuredContext['review']['description'] ? 'Review description: '.$structuredContext['review']['description'] : null,
                'Review status: '.$structuredContext['review']['status'],
                'Owner: '.$structuredContext['review']['owner'],
                'Assignment: '.$structuredContext['review']['assignment'],
            ])),
            'Workflow summary:'.PHP_EOL.implode(PHP_EOL, array_filter([
                'Progress: '.$structuredContext['workflow']['progress'],
                'Current step: '.$structuredContext['workflow']['current_step'],
                $structuredContext['workflow']['current_step_status'] ? 'Current step status: '.$structuredContext['workflow']['current_step_status'] : null,
                $structuredContext['workflow']['step_purpose'] ? 'Current step purpose: '.$structuredContext['workflow']['step_purpose'] : null,
                'Step list:',
                ...$structuredContext['workflow']['step_list'],
            ])),
            'Current step guidance:'.PHP_EOL.implode(PHP_EOL, array_filter([
                $workflowGuidance ? 'Focus: '.$workflowGuidance['title'] : null,
                $workflowGuidance ? 'Best tab: '.$workflowGuidance['tab'] : null,
                $workflowGuidance ? 'Suggested action: '.$workflowGuidance['action'] : null,
                $workflowGuidance ? 'Why: '.$workflowGuidance['summary'] : 'No additional step guidance is available from the current review step.',
            ])),
            'Known gaps:'.PHP_EOL.$this->bulletSection($structuredContext['known_gaps']),
            'Evidence warnings:'.PHP_EOL.$this->bulletSection($structuredContext['evidence_warnings']),
            'Record readiness:'.PHP_EOL.$this->bulletSection($structuredContext['record_readiness']),
            'Grounding layers:'.PHP_EOL.implode(PHP_EOL, [
                'Global reference guidance: '.$this->groundingSection($structuredContext['grounding']['global']),
                'Jurisdiction guidance: '.$this->groundingSection($structuredContext['grounding']['jurisdiction']),
                'Review record and documents: '.$this->groundingSection($structuredContext['grounding']['review']),
            ]),
            'Tab facts:'.PHP_EOL.$this->bulletSection($structuredContext['tab_facts']),
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

    /**
     * @return list<string>
     */
    private function workspaceFacts(PlsReview $review, string $workspaceKey): array
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
            default => ['No tab facts are available.'],
        };
    }

    /**
     * @return list<string>
     */
    private function workflowFacts(PlsReview $review): array
    {
        $documents = $this->documentsForWorkspace($review);

        return array_values(array_filter([
            sprintf(
                'Current record counts: legislation=%d; documents=%d; stakeholders=%d; consultations=%d; submissions=%d; findings=%d; recommendations=%d; reports=%d; government_responses=%d',
                $review->legislation->count(),
                $documents->count(),
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
        ]));
    }

    /**
     * @return list<string>
     */
    private function documentsFacts(PlsReview $review): array
    {
        $documents = $this->documentsForWorkspace($review);

        if ($documents->isEmpty()) {
            return ['No documents are attached to this review yet.'];
        }

        return [
            'Documents attached: '.$documents->count(),
            ...$this->formatList($documents, fn (Document $document): string => sprintf(
                '%s [%s]%s',
                $document->title,
                Str::headline($document->document_type->value),
                $document->summary ? ' Summary: '.$document->summary : '',
            )),
        ];
    }

    /**
     * @return list<string>
     */
    private function legislationFacts(PlsReview $review): array
    {
        if ($review->legislation->isEmpty()) {
            return ['No legislation is linked to this review yet.'];
        }

        return [
            'Linked legislation: '.$review->legislation->count(),
            ...$this->formatList($review->legislation, fn (Legislation $legislation): string => sprintf(
                '%s [%s]%s%s',
                $legislation->title,
                Str::headline($legislation->legislation_type->value),
                $legislation->pivot?->relationship_type ? ' Relationship: '.Str::headline((string) $legislation->pivot->relationship_type) : '',
                $legislation->summary ? ' Summary: '.$legislation->summary : '',
            )),
        ];
    }

    /**
     * @return list<string>
     */
    private function collaboratorFacts(PlsReview $review): array
    {
        if ($review->memberships->isEmpty()) {
            return ['No collaborators are attached to this review yet.'];
        }

        return [
            'Collaborators: '.$review->memberships->count(),
            ...$this->formatList($review->memberships, fn (PlsReviewMembership $membership): string => sprintf(
                '%s [%s]',
                $membership->user?->name ?? 'Unknown user',
                Str::headline($membership->role->value),
            )),
        ];
    }

    /**
     * @return list<string>
     */
    private function stakeholderFacts(PlsReview $review): array
    {
        if ($review->stakeholders->isEmpty()) {
            return ['No stakeholders are recorded yet.'];
        }

        $missingContacts = $review->stakeholders
            ->filter(fn (Stakeholder $stakeholder): bool => blank($stakeholder->contact_details['email'] ?? null) && blank($stakeholder->contact_details['phone'] ?? null));

        return [
            'Stakeholders: '.$review->stakeholders->count(),
            'Implementing agencies: '.$review->implementingAgencies->count(),
            'Stakeholders missing direct contact detail: '.$missingContacts->count(),
            ...$this->formatList($review->stakeholders, fn (Stakeholder $stakeholder): string => sprintf(
                '%s [%s]%s',
                $stakeholder->name,
                Str::headline($stakeholder->stakeholder_type->value),
                $stakeholder->submissions->isNotEmpty() ? ' Submissions: '.$stakeholder->submissions->count() : '',
            )),
        ];
    }

    /**
     * @return list<string>
     */
    private function consultationFacts(PlsReview $review): array
    {
        if ($review->consultations->isEmpty() && $review->submissions->isEmpty()) {
            return ['No consultations or submissions are recorded yet.'];
        }

        $plannedCount = $review->consultations->filter(fn (Consultation $consultation): bool => $consultation->held_at === null)->count();
        $completedCount = $review->consultations->count() - $plannedCount;

        return [
            sprintf(
                'Consultations: total=%d; completed=%d; planned=%d',
                $review->consultations->count(),
                $completedCount,
                $plannedCount,
            ),
            'Submissions: '.$review->submissions->count(),
            ...$this->formatList($review->consultations, fn (Consultation $consultation): string => sprintf(
                '%s [%s]%s%s',
                $consultation->title,
                Str::headline($consultation->consultation_type->value),
                $consultation->held_at ? ' Held: '.$consultation->held_at->toDateString() : ' Planned',
                $consultation->summary ? ' Summary: '.$consultation->summary : '',
            )),
            ...($review->submissions->isEmpty()
                ? ['No submissions are logged yet.']
                : $this->formatList($review->submissions, fn (Submission $submission): string => sprintf(
                    'Submission from %s%s',
                    $submission->stakeholder?->name ?? 'Unknown stakeholder',
                    $submission->summary ? ': '.$submission->summary : '',
                ))),
        ];
    }

    /**
     * @return list<string>
     */
    private function analysisFacts(PlsReview $review): array
    {
        if ($review->findings->isEmpty() && $review->recommendations->isEmpty()) {
            return ['No findings or recommendations are recorded yet.'];
        }

        return [
            'Findings: '.$review->findings->count(),
            'Recommendations: '.$review->recommendations->count(),
            ...($review->findings->isEmpty()
                ? ['No findings are recorded yet.']
                : $this->formatList($review->findings, fn (Finding $finding): string => sprintf(
                    '%s [%s]%s',
                    $finding->title,
                    Str::headline($finding->finding_type->value),
                    $finding->summary ? ' Summary: '.$finding->summary : '',
                ))),
            ...($review->recommendations->isEmpty()
                ? ['No recommendations are recorded yet.']
                : $this->formatList($review->recommendations, fn ($recommendation): string => sprintf(
                    '%s%s',
                    $recommendation->title,
                    $recommendation->description ? ': '.$recommendation->description : '',
                ))),
        ];
    }

    /**
     * @return list<string>
     */
    private function reportFacts(PlsReview $review): array
    {
        if ($review->reports->isEmpty() && $review->governmentResponses->isEmpty()) {
            return ['No reports or government responses are recorded yet.'];
        }

        return [
            'Reports: '.$review->reports->count(),
            'Government responses: '.$review->governmentResponses->count(),
            ...($review->reports->isEmpty()
                ? ['No reports are recorded yet.']
                : $this->formatList($review->reports, fn (Report $report): string => sprintf(
                    '%s [%s | %s]%s',
                    $report->title,
                    Str::headline($report->report_type->value),
                    Str::headline($report->status->value),
                    $report->published_at ? ' Published: '.$report->published_at->toDateString() : '',
                ))),
            ...($review->governmentResponses->isEmpty()
                ? ['No government responses are recorded yet.']
                : $this->formatList($review->governmentResponses, fn (GovernmentResponse $response): string => sprintf(
                    '%s [%s]%s',
                    $response->report?->title ?? 'Unlinked report',
                    Str::headline($response->response_status->value),
                    $response->summary ? ' '.$response->summary : '',
                ))),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return list<string>
     */
    private function formatList(Collection $items, callable $formatter, int $limit = 6): array
    {
        return $items
            ->take($limit)
            ->map($formatter)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function knownGaps(PlsReview $review): array
    {
        $documents = $this->documentsForWorkspace($review);

        return array_values(array_filter([
            $review->legislation->isEmpty() ? 'No legislation is linked to this review.' : null,
            $documents->isEmpty() ? 'No documents are uploaded for this review yet.' : null,
            $review->stakeholders->isEmpty() ? 'No stakeholders are recorded yet.' : null,
            ($review->consultations->isEmpty() && $review->submissions->isEmpty()) ? 'No consultations or submissions are recorded yet.' : null,
            ($review->findings->isEmpty() && $review->current_step_number >= 6) ? 'No findings are recorded even though the review is in or beyond the analysis stage.' : null,
            ($review->reports->isEmpty() && $review->current_step_number >= 7) ? 'No reports are recorded even though the review is in or beyond the reporting stage.' : null,
            ($review->governmentResponses->isEmpty() && $review->current_step_number >= 9) ? 'No government responses are recorded even though the review is in or beyond the response stage.' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    private function evidenceWarnings(PlsReview $review): array
    {
        $documents = $this->documentsForWorkspace($review);

        return array_values(array_filter([
            $documents->isEmpty() ? 'Document-grounded answers will be limited because there are no review documents yet.' : null,
            $review->findings->isEmpty() ? 'Any analytical conclusions would be weak because the findings record is still empty.' : null,
            $review->recommendations->isEmpty() ? 'Recommendation support is limited because no recommendations are recorded yet.' : null,
            ($review->consultations->isEmpty() && $review->submissions->isEmpty()) ? 'Consultation analysis is unsupported because there are no consultation records or submissions.' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    private function recordReadiness(PlsReview $review): array
    {
        $documents = $this->documentsForWorkspace($review);

        return array_values(array_filter([
            $review->legislation->isNotEmpty() ? 'Legislation record present.' : 'Legislation record missing.',
            $documents->isNotEmpty() ? 'Document record present.' : 'Document record missing.',
            $review->stakeholders->isNotEmpty() ? 'Stakeholder record present.' : 'Stakeholder record missing.',
            $review->consultations->isNotEmpty() || $review->submissions->isNotEmpty() ? 'Consultation evidence present.' : 'Consultation evidence missing.',
            $review->findings->isNotEmpty() ? 'Analysis record present.' : null,
            $review->reports->isNotEmpty() ? 'Reporting record present.' : null,
        ]));
    }

    /**
     * @return Collection<int, Document>
     */
    private function documentsForWorkspace(PlsReview $review): Collection
    {
        return $review->documents->reject(
            fn (Document $document): bool => $document->document_type === DocumentType::LegislationText,
        )->values();
    }

    /**
     * @param  list<string>  $items
     */
    private function bulletSection(array $items): string
    {
        if ($items === []) {
            return '- None recorded.';
        }

        return collect($items)
            ->map(fn (string $item): string => '- '.$item)
            ->implode(PHP_EOL);
    }

    /**
     * @param  list<array{excerpt: string, label: string, source: string}>  $references
     */
    private function groundingSection(array $references): string
    {
        if ($references === []) {
            return 'none available';
        }

        return collect($references)
            ->map(fn (array $reference): string => sprintf(
                '%s: %s',
                $reference['label'],
                Str::limit($reference['excerpt'], 180),
            ))
            ->implode(' | ');
    }

    private function groundingPriority(string $prompt): string
    {
        $normalizedPrompt = Str::lower($prompt);

        return match (true) {
            $this->containsAny($normalizedPrompt, ['jurisdiction', 'country', 'legislature', 'parliament', 'local practice', 'standing order', 'template', 'government response norm']) => 'Jurisdiction guidance first, then global reference guidance, then review-specific material only if needed.',
            $this->containsAny($normalizedPrompt, ['workflow', 'process', 'method', 'methodology', 'next step', 'stage']) => 'Jurisdiction guidance where available, then global reference guidance, then review-specific material only when directly relevant.',
            $this->containsAny($normalizedPrompt, ['this review', 'current record', 'uploaded', 'document', 'finding', 'report', 'recommendation']) => 'Review record first, then jurisdiction guidance, then global reference guidance for framing only.',
            default => 'Use and distinguish global guidance, jurisdiction guidance, and the current review record where each is helpful.',
        };
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        return collect($needles)->contains(fn (string $needle): bool => Str::contains($haystack, $needle));
    }
}
