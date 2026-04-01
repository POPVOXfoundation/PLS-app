<?php

namespace App\Domain\Reviews\Support;

use App\Domain\Reviews\PlsReviewStep;

class PlsReviewStepGuidance
{
    /**
     * @var array<string, array{action: string, summary: string, tab: string, title: string}>
     */
    private const GUIDANCE = [
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
        'draft_report' => [
            'title' => 'Prepare the report record',
            'summary' => 'Reports and linked publication documents should now become the source of truth for what this inquiry is releasing.',
            'tab' => 'Reports',
            'action' => 'Create the report record, link the published file, and keep status up to date.',
        ],
        'dissemination' => [
            'title' => 'Prepare the report record',
            'summary' => 'Reports and linked publication documents should now become the source of truth for what this inquiry is releasing.',
            'tab' => 'Reports',
            'action' => 'Create the report record, link the published file, and keep status up to date.',
        ],
        'government_response' => [
            'title' => 'Track what happens after publication',
            'summary' => 'Focus on the reports tab so government responses, linked documents, and follow-up signals stay attached to the final report.',
            'tab' => 'Reports',
            'action' => 'Keep the report record current and log any response request, reply, or overdue follow-up.',
        ],
        'follow_up' => [
            'title' => 'Track what happens after publication',
            'summary' => 'Focus on the reports tab so government responses, linked documents, and follow-up signals stay attached to the final report.',
            'tab' => 'Reports',
            'action' => 'Keep the report record current and log any response request, reply, or overdue follow-up.',
        ],
        'evaluation' => [
            'title' => 'Track what happens after publication',
            'summary' => 'Focus on the reports tab so government responses, linked documents, and follow-up signals stay attached to the final report.',
            'tab' => 'Reports',
            'action' => 'Keep the report record current and log any response request, reply, or overdue follow-up.',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const STEP_CONTEXT = [
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
    ];

    /**
     * @return array{action: string, summary: string, tab: string, title: string}|null
     */
    public function guidanceForStep(PlsReviewStep|string|null $step): ?array
    {
        $stepKey = $this->resolveStepKey($step);

        return $stepKey === null ? null : self::GUIDANCE[$stepKey] ?? null;
    }

    public function contextForStep(PlsReviewStep|string|null $step): string
    {
        $stepKey = $this->resolveStepKey($step);

        return self::STEP_CONTEXT[$stepKey] ?? 'Review the current materials attached to this workflow step.';
    }

    private function resolveStepKey(PlsReviewStep|string|null $step): ?string
    {
        return match (true) {
            $step instanceof PlsReviewStep => $step->step_key,
            is_string($step) && $step !== '' => $step,
            default => null,
        };
    }
}
