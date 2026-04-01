<?php

namespace App\Support\PlsAssistant;

use App\Domain\Reviews\PlsReview;
use Illuminate\Support\Str;

class ReviewAssistantRefusalGuard
{
    /**
     * @param  array{
     *     known_gaps: list<string>,
     *     evidence_warnings: list<string>,
     *     grounding: array{
     *         global: list<array{excerpt: string, label: string, source: string}>,
     *         jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *         review: list<array{excerpt: string, label: string, source: string}>
     *     },
     *     record_readiness: list<string>,
     *     review: array<string, mixed>,
     *     tab_facts: list<string>,
     *     workflow: array<string, mixed>
     * } $structuredContext
     */
    public function refuseOrAllow(
        PlsReview $review,
        string $workspaceKey,
        string $prompt,
        array $structuredContext,
    ): ?string {
        $normalizedPrompt = Str::lower($prompt);

        return match ($workspaceKey) {
            'workflow' => $this->workflowRefusal($normalizedPrompt),
            'documents' => $this->documentsRefusal($normalizedPrompt, $structuredContext),
            'legislation' => $this->legislationRefusal($normalizedPrompt, $structuredContext),
            'stakeholders' => $this->stakeholdersRefusal($normalizedPrompt),
            'consultations' => $this->consultationsRefusal($normalizedPrompt, $review),
            'analysis' => $this->analysisRefusal($normalizedPrompt, $review),
            'reports' => $this->reportsRefusal($normalizedPrompt, $review),
            default => null,
        };
    }

    private function workflowRefusal(string $prompt): ?string
    {
        if ($this->containsAny($prompt, ['finding', 'recommendation', 'legal conclusion', 'impact conclusion', 'policy conclusion'])) {
            return 'I can help with workflow stages and next steps in this tab, but I cannot generate findings, recommendations, or conclusions from the Workflow tab.';
        }

        return null;
    }

    /**
     * @param  array{
     *     grounding: array{
     *         global: list<array{excerpt: string, label: string, source: string}>,
     *         jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *         review: list<array{excerpt: string, label: string, source: string}>
     *     }
     * } $structuredContext
     */
    private function documentsRefusal(string $prompt, array $structuredContext): ?string
    {
        if ($this->containsAny($prompt, ['impact', 'impact analysis', 'policy impact'])) {
            return 'I can summarize and compare documents in this tab, but I cannot provide impact analysis from the Documents tab.';
        }

        $hasGroundedDocuments = collect($structuredContext['grounding']['review'])
            ->merge($structuredContext['grounding']['global'])
            ->merge($structuredContext['grounding']['jurisdiction'])
            ->isNotEmpty();

        if (! $hasGroundedDocuments && $this->containsAny($prompt, ['what does', 'what is in', 'summarize this document', 'compare the documents', 'missing regulation', 'missing regulations'])) {
            return 'I can only make claims about document contents or missing materials here when the current review has supporting uploads or approved reference sources.';
        }

        return null;
    }

    /**
     * @param  array{
     *     grounding: array{
     *         global: list<array{excerpt: string, label: string, source: string}>,
     *         jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *         review: list<array{excerpt: string, label: string, source: string}>
     *     }
     * } $structuredContext
     */
    private function legislationRefusal(string $prompt, array $structuredContext): ?string
    {
        if ($this->containsAny($prompt, ['impact', 'worked', 'effectiveness', 'policy conclusion', 'final conclusion'])) {
            $hasEvidence = collect($structuredContext['grounding']['review'])->isNotEmpty();

            if (! $hasEvidence) {
                return 'I can explain the structure of the legislation in this tab, but I cannot evaluate impact or give final conclusions without supporting evidence.';
            }
        }

        return null;
    }

    private function stakeholdersRefusal(string $prompt): ?string
    {
        if ($this->containsAny($prompt, ['what do stakeholders think', 'who would support', 'who would oppose', 'politically', 'political reaction'])) {
            return 'I can help map stakeholders and identify gaps here, but I cannot assume stakeholder views or make political inferences without grounded source material.';
        }

        return null;
    }

    private function consultationsRefusal(string $prompt, PlsReview $review): ?string
    {
        if (
            $this->containsAny($prompt, ['analyze the consultation results', 'analyse the consultation results', 'what did consultees say', 'what do submissions show'])
            && $review->consultations->isEmpty()
            && $review->submissions->isEmpty()
        ) {
            return 'I can help design consultation activity in this tab, but I cannot analyze consultation results because no consultations or submissions are recorded yet.';
        }

        return null;
    }

    private function analysisRefusal(string $prompt, PlsReview $review): ?string
    {
        if ($this->containsAny($prompt, ['final recommendation', 'final conclusions', 'final finding'])) {
            return 'I can help with provisional findings, themes, and recommendation options in this tab, but I cannot present them as final conclusions.';
        }

        if ($review->findings->isEmpty() && $this->containsAny($prompt, ['recommendation', 'recommendations'])) {
            return 'I can help frame provisional recommendation options, but the current analysis record does not yet contain findings to support definitive recommendations.';
        }

        return null;
    }

    private function reportsRefusal(string $prompt, PlsReview $review): ?string
    {
        if ($this->containsAny($prompt, ['final report', 'publish the final report', 'definitive recommendation', 'final recommendation'])) {
            $hasPublishedReport = $review->reports->contains(fn ($report): bool => $report->published_at !== null);

            if (! $hasPublishedReport) {
                return 'I can help with report structure and provisional drafting here, but I cannot treat this as final publication language until the report record shows a published outcome.';
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        return collect($needles)->contains(fn (string $needle): bool => Str::contains($haystack, $needle));
    }
}
