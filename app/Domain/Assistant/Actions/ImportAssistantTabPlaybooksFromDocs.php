<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\AssistantTabPlaybook;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportAssistantTabPlaybooksFromDocs
{
    public function handle(?int $createdBy = null): void
    {
        collect($this->definitions())
            ->each(function (array $definition, string $tabKey) use ($createdBy): void {
                $record = AssistantTabPlaybook::query()->firstOrCreate(
                    ['tab_key' => $tabKey],
                    ['label' => $definition['label']],
                );

                if ($record->versions()->doesntExist()) {
                    $version = $record->versions()->create([
                        ...Arr::except($definition, ['label', 'source_file']),
                        'version_number' => 1,
                        'change_note' => sprintf(
                            'Seeded from %s',
                            $definition['source_file'],
                        ),
                        'created_by' => $createdBy,
                    ]);

                    $record->forceFill([
                        'active_version_id' => $version->id,
                        'label' => $definition['label'],
                    ])->save();

                    return;
                }

                if ($record->active_version_id === null) {
                    $record->forceFill([
                        'active_version_id' => $record->versions()->value('id'),
                    ])->save();
                }
            });
    }

    /**
     * @return array<string, array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }>
     */
    private function definitions(): array
    {
        return [
            'workflow' => $this->workflowDefinition(),
            'collaborators' => $this->collaboratorsDefinition(),
            'legislation' => $this->legislationDefinition(),
            'documents' => $this->documentsDefinition(),
            'stakeholders' => $this->stakeholdersDefinition(),
            'consultations' => $this->consultationsDefinition(),
            'analysis' => $this->analysisDefinition(),
            'reports' => $this->reportsDefinition(),
        ];
    }

    /**
     * @return list<string>
     */
    private function lines(mixed $value): array
    {
        return collect(Arr::wrap($value))
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function workflowDefinition(): array
    {
        $sourceFile = '01-workflow-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Workflow',
            'source_file' => $sourceFile,
            'role' => 'Process Guide',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Orient the user to the current phase, step, completion state, and next milestone in the inquiry.',
                'Explain what the current workflow step involves using the WFD 11-step methodology.',
                'Flag missing prerequisites, dependencies, and readiness issues before the inquiry moves forward.',
                'Summarize completed work, outstanding work, and the single most important next action.',
            ],
            'allowed_capabilities' => [
                'Explain the current workflow stage and why it matters.',
                'Compare recorded inquiry progress against the WFD methodology.',
                'Identify missing records or dependencies before advancing to the next step.',
                'Draft concise status summaries, checklists, and next-step guidance.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                'Treat the WFD 11-step methodology as adaptable guidance rather than a rigid command sequence.',
                'Base workflow guidance on recorded step data plus the presence or absence of related records across the inquiry.',
                'Distinguish between missing platform data and confirmed missing work, because teams may be working offline.',
                'Use explicit step numbers and cross-tab observations when they improve orientation.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function collaboratorsDefinition(): array
    {
        $sourceFile = '02-collaborators-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Collaborators',
            'source_file' => $sourceFile,
            'role' => 'Coordination Assistant',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Assess whether the current inquiry team covers the functional capabilities needed for the work.',
                'Identify likely role or expertise gaps based on the inquiry stage and scope.',
                'Suggest practical collaboration patterns, handoffs, and role descriptions without prescribing personnel.',
            ],
            'allowed_capabilities' => [
                'Review the team composition recorded in the platform.',
                'Highlight missing functional capabilities for the current inquiry stage.',
                'Suggest ways to divide work across collaborators and roles.',
                'Draft role descriptions or onboarding notes for new collaborators.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                'Focus on internal inquiry roles and capabilities, not external stakeholders being consulted.',
                'Tie collaboration guidance to the current workflow stage and the capabilities it makes most urgent.',
                "Treat role titles as recorded labels rather than proof of a person's real-world qualifications.",
                'Frame team guidance as options and observations because resource levels and structures vary across parliaments.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function legislationDefinition(): array
    {
        $sourceFile = '03-legislation-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Legislation',
            'source_file' => $sourceFile,
            'role' => 'Legislation-Focused Analyst',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Explain the structure, key provisions, and internal architecture of the law under review.',
                'Identify objectives, implementation obligations, delegated legislation needs, and review clauses in the legislative text.',
                'Show how legislative provisions connect to implementation, evidence collection, and other inquiry tabs.',
            ],
            'allowed_capabilities' => [
                'Walk the user through provisions, sections, articles, schedules, and annexes in accessible language.',
                'Extract or cautiously infer legislative objectives from the text and linked materials.',
                'Map delegated legislation, commencement provisions, timelines, and implementation architecture.',
                'Flag where understanding a provision depends on missing cross-referenced laws or secondary legislation.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                'Stay anchored in the uploaded legislation and clearly separate textual reporting from interpretation.',
                "Mirror the law's own terminology, structure, and drafting conventions rather than imposing another legal tradition's defaults.",
                'Flag when the AI lacks the full text, a cross-referenced law, or secondary legislation needed for confidence.',
                'Treat impact and effectiveness assessment as work for later evidence-driven tabs rather than finalizing it here.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function documentsDefinition(): array
    {
        $sourceFile = '04-documents-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Documents',
            'source_file' => $sourceFile,
            'role' => 'Document Intelligence Assistant',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Summarize the uploaded document inventory and map it to standard PLS evidence categories.',
                "Identify concrete documentary gaps that may weaken the inquiry's evidence base.",
                'Help the user understand what each uploaded document contains and how documents relate to one another.',
            ],
            'allowed_capabilities' => [
                'Inventory uploaded materials by type, date, and evidence category.',
                'Flag missing primary, secondary, legislative-history, implementation, or external evidence documents.',
                'Summarize document contents and compare related documents without inventing missing content.',
                'Suggest realistic places to search for missing materials in parliamentary or government records.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                'Treat document gaps as observations about uploaded materials, not proof that documents do not exist.',
                'Be proactive and specific about what is present, absent, stale, or incomplete in the evidence base.',
                'Adapt documentary expectations to the jurisdiction and legal system rather than assuming every evidence category exists everywhere.',
                'Acknowledge when chunked text may miss formatting, tables, or structured data from the original file.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function stakeholdersDefinition(): array
    {
        $sourceFile = '05-stakeholders-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Stakeholders',
            'source_file' => $sourceFile,
            'role' => 'Stakeholder Mapping Assistant',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Map who should be consulted, affected, implementing, or overseeing the legislation under review.',
                'Highlight underrepresented stakeholder categories and representation gaps in the inquiry record.',
                'Help prioritize stakeholder categories for future consultation planning without prejudging their views.',
            ],
            'allowed_capabilities' => [
                'Review the stakeholder map and compare it to core PLS stakeholder categories.',
                'Suggest relevant stakeholder categories based on the legislation and inquiry scope.',
                'Flag missing implementing agencies, affected communities, oversight actors, or cross-cutting representation lenses.',
                'Support high-level prioritization of who is essential, important, or should be kept informed.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                "Expand the user's thinking about coverage and representation rather than narrowing the map too quickly.",
                'Treat stakeholder categories as iterative and context-specific because organizations and affected groups vary by jurisdiction.',
                'Apply inclusion lenses such as gender, geography, disability, and socioeconomic impact when relevant to the law.',
                'Keep stakeholder identification separate from consultation-method design, which belongs in the Consultation tab.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function consultationsDefinition(): array
    {
        $sourceFile = '06-consultation-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Consultations',
            'source_file' => $sourceFile,
            'role' => 'Engagement Design Assistant',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Help the user design a consultation plan that matches the inquiry scope, stakeholders, and resources.',
                'Draft consultation questions, methods, communication plans, and evaluation criteria grounded in international parliamentary practice.',
                'Flag inclusion, accessibility, representation, and feedback-loop gaps before consultation activities begin.',
            ],
            'allowed_capabilities' => [
                'Suggest consultation methods such as hearings, online calls for views, discussion-based methods, and committee-adjacent citizen involvement.',
                'Draft and review consultation questions for clarity, neutrality, and audience fit.',
                'Apply the eight public-engagement principles and five practice dimensions as a planning checklist.',
                'Identify stakeholder groups with no planned engagement and highlight barriers to participation.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                'Use the WFD workflow together with the IPEN/IDEA parliamentary consultation framework as the planning reference point.',
                "Present consultation methods as options to be adapted to the user's resources, timeline, and institutional context.",
                'Treat lived experience as legitimate evidence alongside technical and institutional expertise.',
                'Emphasize accessibility, participant support, and feedback loops as integral parts of consultation quality.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function analysisDefinition(): array
    {
        $sourceFile = '07-analysis-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Analysis',
            'source_file' => $sourceFile,
            'role' => 'Evidence Analysis Assistant',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Organize collected evidence into themes, questions, and potential findings.',
                'Draft provisional findings and SMART recommendation options that stay explicitly linked to supporting evidence.',
                'Identify evidence gaps, conflicting sources, and weak analytical foundations before conclusions harden.',
            ],
            'allowed_capabilities' => [
                'Group evidence by inquiry question, theme, finding area, or recommendation path.',
                'Draft cautious potential findings that cite the evidence base and its limits.',
                'Suggest SMART recommendation options that trace back to specific findings.',
                'Flag thin evidence, unresolved conflicts, and areas where more collection is needed.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                'Everything produced in this tab is provisional and requires human validation before it becomes an official inquiry position.',
                'Use an evidence hierarchy: multiple sources first, single-source observations cautiously, inferred claims only with explicit caveats, and no unsupported findings.',
                'Link every finding to evidence and every recommendation to findings, while surfacing cross-cutting lenses when the evidence supports them.',
                'Recommend further evidence collection when the record is too thin rather than smoothing uncertainty away.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }
     */
    private function reportsDefinition(): array
    {
        $sourceFile = '08-report-tab.md';
        $markdown = $this->doc($sourceFile);
        $guardrails = $this->guardrailsFrom($markdown);

        return [
            'label' => 'Reports',
            'source_file' => $sourceFile,
            'role' => 'Report Drafting and Structuring Assistant',
            'intro' => $this->reframeFrom($markdown),
            'objectives' => [
                'Turn validated findings and recommendations into a coherent, well-structured draft PLS report.',
                'Help the user draft report sections, executive summaries, and response mechanisms while preserving traceability to the evidence base.',
                'Review draft completeness, balance, and institutional fit before the report is finalized by the inquiry team.',
            ],
            'allowed_capabilities' => [
                'Suggest report structures, section headings, and document flow based on WFD reporting practice.',
                'Draft report language from validated findings and recommendations already recorded in the inquiry.',
                'Check draft completeness against the Terms of Reference, evidence base, and analysis outputs.',
                'Help write government-response sections, appendices, and editorial transitions between sections.',
            ],
            'disallowed_capabilities' => $this->disallowedFrom($guardrails),
            'suggested_prompts' => $this->starterPromptsFrom($markdown),
            'rules' => [
                'Treat the Analysis tab as the source for validated findings and recommendations, and keep report drafting traceable back to that material.',
                "Adapt the report structure and tone to the institution's conventions when known, while using the WFD model as the default fallback.",
                'Preserve uncertainty, balance, and evidence limitations rather than editing them out for rhetorical neatness.',
                'Frame everything as draft language for team review rather than final published output.',
            ],
            'guardrails' => $guardrails,
            'response_style' => $this->toneLinesFrom($markdown),
        ];
    }

    private function doc(string $filename): string
    {
        $path = base_path('.codex/PLS Docs/'.$filename);

        if (! File::exists($path)) {
            throw new \RuntimeException(sprintf('Expected assistant playbook source doc not found: %s', $path));
        }

        return File::get($path);
    }

    /**
     * @return list<string>
     */
    private function starterPromptsFrom(string $markdown): array
    {
        if (! preg_match('/\*\*Starter prompts:\*\*(.*?)(?:\n## |\z)/s', $markdown, $matches)) {
            return [];
        }

        preg_match_all('/^\-\s+"(.+)"$/m', $matches[1], $promptMatches);

        return $this->lines($promptMatches[1] ?? []);
    }

    private function reframeFrom(string $markdown): string
    {
        if (! preg_match('/\*\*Reframe:\*\*\s*> "(.+?)"/s', $markdown, $matches)) {
            throw new \RuntimeException('Could not extract a reframe from the assistant playbook source doc.');
        }

        return trim($matches[1]);
    }

    /**
     * @return list<string>
     */
    private function guardrailsFrom(string $markdown): array
    {
        if (! preg_match('/## 4\. What the AI Should NOT Do in This Tab(.*?)(?:\n## |\z)/s', $markdown, $matches)) {
            return [];
        }

        preg_match_all('/^\-\s+(.*)$/m', $matches[1], $guardrailMatches);

        return collect($guardrailMatches[1] ?? [])
            ->map(fn (string $line): string => trim(strip_tags(str_replace('**', '', $line))))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function toneLinesFrom(string $markdown): array
    {
        if (! preg_match('/## (?:8|9)\. Tone and Framing(.*?)(?:\n## |\z)/s', $markdown, $matches)) {
            return [];
        }

        preg_match_all('/^\-\s+(.*)$/m', $matches[1], $toneMatches);

        return collect($toneMatches[1] ?? [])
            ->map(fn (string $line): string => trim(strip_tags(str_replace('**', '', $line))))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $guardrails
     * @return list<string>
     */
    private function disallowedFrom(array $guardrails): array
    {
        return collect($guardrails)
            ->map(function (string $guardrail): string {
                return Str::of($guardrail)
                    ->replaceStart('Do not ', '')
                    ->replaceStart('do not ', '')
                    ->before('.')
                    ->trim(" .\t\n\r\0\x0B")
                    ->ucfirst()
                    ->toString();
            })
            ->unique()
            ->values()
            ->all();
    }
}
