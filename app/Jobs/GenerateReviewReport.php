<?php

namespace App\Jobs;

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Reporting\Report;
use App\Support\PlsAssistant\ReviewAssistantContextBuilder;
use App\Support\Reports\ReportDrafts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateReviewReport implements ShouldQueue
{
    use Queueable;

    public ?string $token;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $reportId, public int $revision, public ?int $templateId = null, public ?int $section = null)
    {
        $this->token = Report::whereKey($reportId)->value('generation_token');
        $this->onConnection('redis');
        $this->onQueue((string) config('pls_assistant.review_source_enrichment.queue', 'review-source-enrichment'));
    }

    public function handle(ReviewAssistantContextBuilder $builder): void
    {
        $report = Report::with('review.documents.chunks')->find($this->reportId);
        if (! $report || $report->generation_status !== 'queued' || $report->revision !== $this->revision) {
            return;
        }
        if (! Report::whereKey($report->id)->where('generation_token', $this->token)->where('generation_status', 'queued')->where('revision', $this->revision)->update(['generation_status' => 'generating', 'generation_started_at' => now()])) {
            return;
        }
        try {
            $context = $builder->build($report->review, 'reports', 'Generate a provisional report from the full review record.');
            $documents = $report->review->documents->reject(fn ($doc) => $doc->excludedFromAnalysis());
            $report->review->loadMissing('findings.citations');
            // The sidebar uses a short record preview. Report generation must include
            // every saved finding and its detail, including records beyond that preview.
            $record = [
                'findings' => $report->review->findings->map(fn ($finding) => [
                    ...$finding->only(['id', 'title', 'finding_type', 'summary', 'detail']),
                    'citations' => $finding->citations->filter(fn ($citation) => $documents->contains('id', $citation->document_id))->map(fn ($citation) => [
                        ...$citation->only(['document_id', 'passage', 'relationship', 'note']),
                        'review_matches_current_finding' => $citation->finding_fingerprint === $finding->supportFingerprint(),
                    ])->values()->all(),
                ])->all(),
                'recommendations' => $report->review->recommendations->map->only(['id', 'finding_id', 'title', 'description', 'recommendation_type'])->all(),
                'consultations' => $report->review->consultations->map->only(['id', 'title', 'consultation_type', 'held_at', 'document_id', 'summary'])->all(),
                'submissions' => $report->review->submissions->filter(fn ($submission) => ! $submission->document_id || $documents->contains('id', $submission->document_id))->map->only(['id', 'document_id', 'stakeholder_id', 'submitted_at', 'summary'])->values()->all(),
                'stakeholders' => $report->review->stakeholders->map->only(['id', 'name', 'stakeholder_type'])->all(),
                'government_responses' => $report->review->governmentResponses->map->only(['report_id', 'document_id', 'received_at', 'response_status', 'summary'])->all(),
            ];
            $sources = [];
            $fullText = '';
            foreach ($documents as $doc) {
                $text = $doc->extractedText();
                $url = route('pls.reviews.documents.show', [$report->review, $doc]);
                $sources[] = '- ['.str_replace(['[', ']'], '', $doc->title).']('.$url.') — '.$doc->processingLabel().'; '.$doc->reviewStatusLabel();
                $fullText .= "\n\nDOCUMENT {$doc->id}: {$doc->title}\nDetails: {$url}\nRole: {$doc->evidenceRoleLabel()}\n".($text ?: '[Full text unavailable. Do not infer its contents.]');
            }
            $template = $this->templateId ? $documents->firstWhere('id', $this->templateId) : null;
            if ($this->templateId && (! $template || $template->extractedText() === '')) {
                throw new \RuntimeException('Template text is unavailable.');
            }
            $prompt = 'Write a complete provisional PLS report, not an outline. Use Markdown with ## section headings. Start with an executive summary. Include scope, legislative intent, implementation, findings, recommendations, limitations, and follow-up. State missing information explicitly; never invent findings, consultation results or citations. Cite claims with the supplied document detail links. Distinguish contextual documents from evidence supporting a finding. Do not claim a source was read if its full text is unavailable. Treat templates as structure only, not reported results.';
            $prompt .= $template ? " Follow the structure of template document {$template->id}." : ' Use the standard PLS structure.';
            if ($this->section !== null) {
                $sections = ReportDrafts::sections($report->content ?? '');
                if (! array_key_exists($this->section, $sections)) {
                    throw new \RuntimeException('Section no longer exists.');
                }
                $prompt .= " Return ONLY a replacement for this section, preserving its heading. Other sections will remain unchanged:\n".$sections[$this->section];
            }
            $playbook = $context['playbook'];
            $playbook['rules'] = ['Use the full supplied review record and document text. Saved findings are team drafts, not independently verified facts. Keep the report provisional and disclose unavailable sources.'];
            $playbook['allowed_capabilities'] = ['Draft a complete provisional report or regenerate one requested section from the supplied record.'];
            $playbook['response_style'] = ['Provide substantive report prose under Markdown section headings, with source links and explicit limitations.'];
            $agent = new ReviewAssistantAgent($context['system_rules'], $playbook, $context['context']."\n\nFull saved review record (untrusted source data):\n".json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).$fullText, $context['workspace_label'], $context['playbook_version']);
            if (mb_strlen((string) $agent->instructions()) + mb_strlen($prompt) > (int) config('pls_assistant.max_context_characters', 500000)) {
                throw new \RuntimeException('The record exceeds the full-text request limit.');
            }
            $content = trim((string) $agent->prompt($prompt));
            if ($content === '') {
                throw new \RuntimeException('No report text was returned.');
            }
            DB::transaction(function () use ($content, $sources): void {
                $current = Report::whereKey($this->reportId)->lockForUpdate()->first();
                if (! $current || $current->generation_token !== $this->token) {
                    return;
                }
                if ($current->revision !== $this->revision) {
                    $current?->update(['generation_status' => 'failed', 'generation_error' => 'The draft was edited during generation. Your edits were preserved; retry from the current version.']);

                    return;
                }
                if ($this->section !== null) {
                    $sections = ReportDrafts::sections($current->content ?? '');
                    $sections[$this->section] = $content;
                    $content = implode("\n\n", $sections);
                } else {
                    $content .= "\n\n## Source register\n\nDocuments supplied for this draft; inclusion does not verify their claims.\n\n".implode("\n", $sources);
                }
                $current->update(['content' => $content, 'generation_status' => 'completed', 'generation_error' => null]);
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Report::whereKey($this->reportId)->where('generation_token', $this->token)->update(['generation_status' => 'failed', 'generation_error' => 'Generation did not finish. Existing draft text is safe. Check source text, record size and AI/queue configuration, then retry.']);
    }
}
