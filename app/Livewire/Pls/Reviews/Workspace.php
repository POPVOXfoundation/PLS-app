<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class Workspace extends Component
{
    use AuthorizesRequests;

    public PlsReview $review;

    protected string $workspace = 'workflow';

    public function mount(PlsReview $review): void
    {
        $this->authorize('view', $review);

        $this->review = $review;
    }

    #[On('review-workspace-updated')]
    public function refreshReviewWorkspace(?string $status = null): void
    {
        $this->review = $this->review->fresh();

        if ($status !== null && $status !== '') {
            session()->flash('status', $status);
        }
    }

    protected function renderWorkspaceView(string $view, array $data, PlsReview $review): View
    {
        return view($view, array_merge($data, [
            'review' => $review,
        ]))->layout('livewire.pls.reviews.workspace', $this->workspaceLayoutData($review));
    }

    /**
     * @return array{
     *     assistantWorkspaceContext: array<string, array{title: string, text: string, prompts: list<string>}>,
     *     currentWorkspaceKey: string,
     *     review: PlsReview,
     *     title: string,
     *     workflowSummary: array{current_step_number: int, current_step: string, total_steps: int, progress_percentage: int},
     *     workspaceNavigation: list<array{icon: string, key: string, label: string, route: string}>
     * }
     */
    protected function workspaceLayoutData(PlsReview $review): array
    {
        return [
            'assistantWorkspaceContext' => $this->assistantWorkspaceContext(),
            'currentWorkspaceKey' => $this->workspace,
            'review' => $review,
            'title' => $review->title,
            'workflowSummary' => $this->workflowSummary($review),
            'workspaceNavigation' => $this->workspaceNavigation(),
        ];
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
                'action' => __('Link the governing law and upload the initial briefing, bill text, or background pack.'),
            ],
            'background_data_plan' => [
                'title' => __('Build the evidence base'),
                'summary' => __('Use the documents area to collect background papers, implementation records, and supporting material before consultations begin.'),
                'tab' => __('Documents'),
                'action' => __('Upload implementation reports, audits, and background research notes.'),
            ],
            'stakeholder_plan' => [
                'title' => __('Map the people and institutions to involve'),
                'summary' => __('The workspace is ready for stakeholder records and the documents that explain why each voice matters to the review.'),
                'tab' => __('Stakeholders'),
                'action' => __('Add priority stakeholders and capture any supporting briefing documents.'),
            ],
            'implementation_review' => [
                'title' => __('Assess implementation delivery'),
                'summary' => __('Keep agencies, supporting documents, and early findings in sync while the review examines how the law is working in practice.'),
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
                'summary' => __('Reports and linked publication documents should now become the source of truth for what this inquiry is releasing.'),
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

    /**
     * @return array{current_step_number: int, current_step: string, total_steps: int, progress_percentage: int}
     */
    public function workflowSummary(PlsReview $review): array
    {
        return [
            'current_step_number' => $review->current_step_number,
            'current_step' => $review->currentStepTitle(),
            'total_steps' => $review->steps->count(),
            'progress_percentage' => $review->progressPercentage(),
        ];
    }

    /**
     * @return list<array{icon: string, key: string, label: string, route: string}>
     */
    public function workspaceNavigation(): array
    {
        return [
            [
                'key' => 'workflow',
                'label' => __('Workflow'),
                'icon' => 'list-bullet',
                'route' => 'pls.reviews.workflow',
            ],
            [
                'key' => 'collaborators',
                'label' => __('Collaborators'),
                'icon' => 'user-plus',
                'route' => 'pls.reviews.collaborators',
            ],
            [
                'key' => 'legislation',
                'label' => __('Legislation'),
                'icon' => 'scale',
                'route' => 'pls.reviews.legislation',
            ],
            [
                'key' => 'documents',
                'label' => __('Documents'),
                'icon' => 'document-text',
                'route' => 'pls.reviews.documents',
            ],
            [
                'key' => 'stakeholders',
                'label' => __('Stakeholders'),
                'icon' => 'users',
                'route' => 'pls.reviews.stakeholders',
            ],
            [
                'key' => 'consultations',
                'label' => __('Consultations'),
                'icon' => 'chat-bubble-left-right',
                'route' => 'pls.reviews.consultations',
            ],
            [
                'key' => 'analysis',
                'label' => __('Analysis'),
                'icon' => 'light-bulb',
                'route' => 'pls.reviews.analysis',
            ],
            [
                'key' => 'reports',
                'label' => __('Reports'),
                'icon' => 'clipboard-document-list',
                'route' => 'pls.reviews.reports',
            ],
        ];
    }

    /**
     * @return array<string, array{title: string, text: string, prompts: list<string>}>
     */
    public function assistantWorkspaceContext(): array
    {
        return [
            'workflow' => [
                'title' => __('Keep the review moving'),
                'text' => __('Use the assistant to summarize the current step, turn status into next actions, or draft quick team updates.'),
                'prompts' => [
                    __('Summarize the current step and what should happen next.'),
                    __('Draft a checklist for the current step.'),
                    __('Turn the workflow status into a short collaborator update.'),
                ],
            ],
            'collaborators' => [
                'title' => __('Coordinate review access'),
                'text' => __('Use the assistant to summarize roles, access decisions, and onboarding notes for the review team.'),
                'prompts' => [
                    __('Summarize who currently has access to this review.'),
                    __('Draft an onboarding note for a new collaborator.'),
                    __('List the access decisions that still need an owner.'),
                ],
            ],
            'legislation' => [
                'title' => __('Ground the review in the law'),
                'text' => __('Use the assistant to summarize linked legislation, compare scope records, or draft plain-language legal briefs.'),
                'prompts' => [
                    __('Summarize the legislation already linked to this review.'),
                    __('Draft a plain-language scope note for the team.'),
                    __('List the legal records that still look incomplete.'),
                ],
            ],
            'documents' => [
                'title' => __('Work from the review record'),
                'text' => __('Use the assistant to outline evidence packs, summarize uploaded material, or identify obvious document gaps.'),
                'prompts' => [
                    __('Summarize the working documents attached so far.'),
                    __('List the missing documents the team may still need.'),
                    __('Draft a briefing note from the current document set.'),
                ],
            ],
            'stakeholders' => [
                'title' => __('Track who matters to the review'),
                'text' => __('Use the assistant to organize outreach priorities, summarize stakeholder coverage, or draft contact plans.'),
                'prompts' => [
                    __('Summarize the stakeholder groups already mapped.'),
                    __('Draft an outreach plan for priority stakeholders.'),
                    __('List which records still need contact detail or evidence.'),
                ],
            ],
            'consultations' => [
                'title' => __('Synthesize engagement activity'),
                'text' => __('Use the assistant to summarize consultation activity, extract themes from submissions, or prepare hearing notes.'),
                'prompts' => [
                    __('Summarize the consultation activity completed so far.'),
                    __('List the strongest themes emerging from submissions.'),
                    __('Draft a short hearing brief from the current record.'),
                ],
            ],
            'analysis' => [
                'title' => __('Turn evidence into findings'),
                'text' => __('Use the assistant to cluster evidence themes, tighten recommendation language, or prepare concise analysis summaries.'),
                'prompts' => [
                    __('Summarize the strongest findings and recommendations so far.'),
                    __('Group the current findings into three themes.'),
                    __('Draft sharper wording for the existing recommendations.'),
                ],
            ],
            'reports' => [
                'title' => __('Support report drafting and follow-up'),
                'text' => __('Use the assistant to summarize publication status, draft executive summaries, or outline response follow-up.'),
                'prompts' => [
                    __('Summarize the current report and response status.'),
                    __('Draft an executive summary from the report record.'),
                    __('List the follow-up actions tied to published reports.'),
                ],
            ],
        ];
    }
}
