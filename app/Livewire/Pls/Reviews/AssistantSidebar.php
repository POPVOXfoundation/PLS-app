<?php

namespace App\Livewire\Pls\Reviews;

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Reviews\PlsReview;
use App\Support\PlsAssistant\ReviewAssistantContextBuilder;
use App\Support\PlsAssistant\ReviewAssistantRefusalGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class AssistantSidebar extends Component
{
    use AuthorizesRequests;

    public PlsReview $review;

    public string $workspaceKey = 'workflow';

    public string $assistantInput = '';

    public ?string $assistantConversationId = null;

    /**
     * @var list<array{content: string, role: string}>
     */
    public array $assistantMessages = [];

    public ?string $assistantError = null;

    public bool $shouldSendProvisionalFindingsToInbox = false;

    public bool $shouldSendReportOutlineToWorkspace = false;

    public function mount(PlsReview $review, string $workspaceKey): void
    {
        $this->authorize('view', $review);

        $this->review = $this->contextBuilder()->hydrateReview($review);
        $this->workspaceKey = $this->contextBuilder()->resolveWorkspaceKey($workspaceKey);
        $this->bootConversationState();
    }

    #[On('review-workspace-updated')]
    public function refreshAssistant(): void
    {
        $this->review = $this->contextBuilder()->hydrateReview($this->review->fresh());
        $this->assistantMessages = $this->conversationMessagesForDisplay();
    }

    public function submitAssistantPrompt(): void
    {
        $prompt = Str::of($this->assistantInput)->trim()->toString();
        $sendProvisionalFindingsToInbox = $this->shouldSendProvisionalFindingsToInbox && $this->workspaceKey === 'analysis';
        $sendReportOutlineToWorkspace = $this->shouldSendReportOutlineToWorkspace && $this->workspaceKey === 'reports';
        $this->shouldSendProvisionalFindingsToInbox = false;
        $this->shouldSendReportOutlineToWorkspace = false;

        if ($prompt === '') {
            return;
        }

        $this->assistantInput = '';
        $this->assistantError = null;

        $this->appendLocalUserMessage($prompt);
        $this->dispatch('assistant-message-added');

        $context = $this->contextBuilder()->build($this->review, $this->workspaceKey, $prompt);
        $agent = $this->assistantAgent($context);

        $refusal = $this->refusalGuard()->refuseOrAllow(
            $this->review,
            $this->workspaceKey,
            $prompt,
            $context['structured_context'],
        );

        if ($refusal !== null) {
            $this->persistSyntheticAssistantExchange($agent, $prompt, $refusal, $context['playbook_version']);
            $this->assistantMessages = $this->conversationMessagesForDisplay();
            $this->sendProvisionalFindingsToInbox($sendProvisionalFindingsToInbox);
            $this->sendReportOutlineToWorkspace($sendReportOutlineToWorkspace);
            $this->dispatch('assistant-message-added', focus: 'response');

            return;
        }

        if ($this->assistantConversationId !== null) {
            $agent->continue($this->assistantConversationId, auth()->user());
        } else {
            $agent->forUser(auth()->user());
        }

        try {
            $response = $agent->prompt($prompt);
        } catch (Throwable $exception) {
            report($exception);

            $this->assistantError = __('The assistant is unavailable right now. Check the AI provider configuration or try again.');

            if ($sendProvisionalFindingsToInbox) {
                $this->dispatch('provisional-findings-failed')->to(AnalysisPage::class);
            }

            if ($sendReportOutlineToWorkspace) {
                $this->dispatch('report-outline-failed')->to(ReportsPage::class);
            }

            $this->dispatch('assistant-message-added');

            return;
        }

        $this->assistantConversationId = $agent->currentConversation();

        $this->storePromptExchangeIfMissing($agent, $prompt, $response);

        if ($this->assistantConversationId !== null) {
            $this->persistConversation($this->assistantConversationId, $context['playbook_version']);
        }

        $this->assistantMessages = $this->conversationMessagesForDisplay();
        $this->sendProvisionalFindingsToInbox($sendProvisionalFindingsToInbox, (string) $response);
        $this->sendReportOutlineToWorkspace($sendReportOutlineToWorkspace);

        $this->dispatch('assistant-message-added', focus: 'response');
    }

    public function sendPrompt(string $prompt, bool $provisionalFindings = false, bool $reportOutline = false): void
    {
        $this->assistantInput = $prompt;
        $this->shouldSendProvisionalFindingsToInbox = $provisionalFindings;
        $this->shouldSendReportOutlineToWorkspace = $reportOutline;

        $this->submitAssistantPrompt();
    }

    #[On('assistant-prompt-requested')]
    public function sendRequestedPrompt(string $prompt, bool $provisionalFindings = false, bool $reportOutline = false): void
    {
        $prompt = Str::of($prompt)->trim()->limit(2000, '')->toString();

        if ($prompt === '') {
            return;
        }

        $this->sendPrompt($prompt, $provisionalFindings, $reportOutline);
    }

    public function assistantPlaceholder(string $workspaceKey): string
    {
        return match ($workspaceKey) {
            'legislation' => __('Ask about this legislation...'),
            'documents' => __('Ask about this evidence...'),
            'stakeholders' => __('Ask about stakeholder coverage...'),
            'consultations' => __('Ask about this consultation process...'),
            'analysis' => __('Ask about findings or recommendations...'),
            'reports' => __('Ask about report drafting...'),
            'settings' => __('Ask about workspace access...'),
            default => __('Ask about this review...'),
        };
    }

    public function resetAssistantConversation(): void
    {
        $conversationIds = DB::table('agent_conversations')
            ->where('pls_review_id', $this->review->getKey())
            ->where('user_id', auth()->id())
            ->pluck('id');

        DB::transaction(function () use ($conversationIds): void {
            if ($conversationIds->isNotEmpty()) {
                DB::table('agent_conversation_messages')
                    ->whereIn('conversation_id', $conversationIds)
                    ->delete();

                DB::table('agent_conversations')
                    ->whereIn('id', $conversationIds)
                    ->delete();
            }
        });

        $this->assistantConversationId = null;
        $this->assistantMessages = [];
        $this->assistantInput = '';
        $this->assistantError = null;

        $this->dispatch('assistant-message-added');
    }

    public function render(): View
    {
        return view('livewire.pls.reviews.assistant-sidebar', [
            'assistantContext' => $this->contextBuilder()->build($this->review, $this->workspaceKey),
        ]);
    }

    private function bootConversationState(): void
    {
        $record = DB::table('agent_conversations')
            ->where('pls_review_id', $this->review->getKey())
            ->where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->first();

        $this->assistantConversationId = $record?->id;
        $this->assistantMessages = $this->conversationMessagesForDisplay();
    }

    private function persistConversation(string $conversationId, string $playbookVersion): void
    {
        DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->update([
                'pls_review_id' => $this->review->getKey(),
                'playbook_version' => $playbookVersion,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return list<array{content: string, role: string}>
     */
    private function conversationMessagesForDisplay(): array
    {
        if ($this->assistantConversationId === null) {
            return $this->assistantMessages;
        }

        $messages = resolve(ConversationStore::class)
            ->getLatestConversationMessages($this->assistantConversationId, 20)
            ->filter(fn (Message $message): bool => in_array($message->role->value, ['assistant', 'user'], true))
            ->map(fn (Message $message): array => [
                'content' => $this->messageContentForDisplay(
                    $message->role->value,
                    (string) $message->content,
                ),
                'role' => $message->role->value,
            ])
            ->values()
            ->all();

        return is_array($messages) ? $messages : [];
    }

    private function appendLocalUserMessage(string $prompt): void
    {
        $this->assistantMessages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];
    }

    private function sendProvisionalFindingsToInbox(bool $sendProvisionalFindingsToInbox, ?string $generatedContent = null): void
    {
        if (! $sendProvisionalFindingsToInbox) {
            return;
        }

        $content = $generatedContent ?? (
            collect($this->assistantMessages)
                ->reverse()
                ->first(fn (array $message): bool => $message['role'] === 'assistant')['content'] ?? null
        );

        if (! is_string($content) || trim($content) === '') {
            $this->dispatch('provisional-findings-failed')->to(AnalysisPage::class);

            return;
        }

        $this->dispatch('provisional-findings-generated', content: $content)
            ->to(AnalysisPage::class);
    }

    private function messageContentForDisplay(string $role, string $content): string
    {
        if ($this->workspaceKey === 'analysis' && str_contains($content, 'POTENTIAL FINDING:')) {
            return $role === 'assistant'
                ? __('Provisional findings have been prepared and added to the Analysis workspace for review.')
                : __('Suggest provisional findings');
        }

        return $role === 'assistant'
            ? $this->normalizeAssistantMessage($content)
            : $content;
    }

    private function sendReportOutlineToWorkspace(bool $sendReportOutlineToWorkspace): void
    {
        if (! $sendReportOutlineToWorkspace) {
            return;
        }

        $content = collect($this->assistantMessages)
            ->reverse()
            ->first(fn (array $message): bool => $message['role'] === 'assistant')['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            $this->dispatch('report-outline-failed')->to(ReportsPage::class);

            return;
        }

        $this->dispatch('report-outline-generated', content: $content)
            ->to(ReportsPage::class);
    }

    private function normalizeAssistantMessage(string $content): string
    {
        $normalized = str($content)
            ->replace('**', '')
            ->replace('__', '')
            ->replaceMatches('/^#{1,6}\s*/m', '')
            ->replaceMatches('/^\s*[-*]\s+/m', '- ')
            ->replaceMatches('/^\s*\d+\.\s+/m', '- ')
            ->trim()
            ->toString();

        return preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
    }

    /**
     * @return list<array{type: 'heading'|'list'|'text', content?: string, items?: list<string>}>
     */
    public function assistantMessageBlocks(string $content): array
    {
        $blocks = [];
        $listItems = [];
        $paragraphLines = [];

        $flushList = function () use (&$blocks, &$listItems): void {
            if ($listItems === []) {
                return;
            }

            $blocks[] = [
                'type' => 'list',
                'items' => $listItems,
            ];

            $listItems = [];
        };

        $flushParagraph = function () use (&$blocks, &$paragraphLines): void {
            if ($paragraphLines === []) {
                return;
            }

            $blocks[] = [
                'type' => 'text',
                'content' => implode(' ', $paragraphLines),
            ];

            $paragraphLines = [];
        };

        foreach (preg_split("/\R/", trim($content)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                $flushParagraph();
                $flushList();

                continue;
            }

            if (str_starts_with($line, '- ')) {
                $flushParagraph();
                $listItems[] = Str::after($line, '- ');

                continue;
            }

            $flushList();

            if (str_ends_with($line, ':')) {
                $flushParagraph();

                $blocks[] = [
                    'type' => 'heading',
                    'content' => $line,
                ];

                continue;
            }

            if ($this->isStandaloneAssistantLine($line)) {
                $flushParagraph();

                $blocks[] = [
                    'type' => 'text',
                    'content' => $line,
                ];

                continue;
            }

            $paragraphLines[] = $line;
        }

        $flushParagraph();
        $flushList();

        return $blocks;
    }

    private function isStandaloneAssistantLine(string $line): bool
    {
        return preg_match('/^[A-Z][A-Za-z0-9()\/,\-\s]{0,60}:\s.+$/', $line) === 1;
    }

    private function storePromptExchangeIfMissing(ReviewAssistantAgent $agent, string $prompt, AgentResponse $response): void
    {
        $existingConversationId = $this->assistantConversationId;
        $store = resolve(ConversationStore::class);

        if (
            $existingConversationId !== null
            && $store->getLatestConversationMessages($existingConversationId, 1)->isNotEmpty()
        ) {
            return;
        }

        $provider = Ai::textProviderFor($agent, config('ai.default'));
        $agentPrompt = new AgentPrompt(
            $agent,
            $prompt,
            [],
            $provider,
            $provider->defaultTextModel(),
        );

        $conversationId = $existingConversationId
            ?? $store->storeConversation(
                auth()->id(),
                Str::limit($prompt, 100, preserveWords: true),
            );

        $store->storeUserMessage($conversationId, auth()->id(), $agentPrompt);
        $store->storeAssistantMessage($conversationId, auth()->id(), $agentPrompt, $response);

        $this->assistantConversationId = $conversationId;
    }

    private function persistSyntheticAssistantExchange(
        ReviewAssistantAgent $agent,
        string $prompt,
        string $assistantReply,
        string $playbookVersion,
    ): void {
        $store = resolve(ConversationStore::class);
        $provider = Ai::textProviderFor($agent, config('ai.default'));
        $agentPrompt = new AgentPrompt(
            $agent,
            $prompt,
            [],
            $provider,
            $provider->defaultTextModel(),
        );

        $conversationId = $this->assistantConversationId
            ?? $store->storeConversation(
                auth()->id(),
                Str::limit($prompt, 100, preserveWords: true),
            );

        $store->storeUserMessage($conversationId, auth()->id(), $agentPrompt);
        $store->storeAssistantMessage(
            $conversationId,
            auth()->id(),
            $agentPrompt,
            new AgentResponse(
                (string) Str::uuid(),
                $assistantReply,
                new Usage,
                new Meta($provider->name(), $provider->defaultTextModel()),
            ),
        );

        $this->assistantConversationId = $conversationId;
        $this->persistConversation($conversationId, $playbookVersion);
    }

    /**
     * @param  array{
     *     context: string,
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
     *     system_rules: list<string>,
     *     workspace_label: string
     * } $context
     */
    private function assistantAgent(array $context): ReviewAssistantAgent
    {
        return new ReviewAssistantAgent(
            systemRules: $context['system_rules'],
            playbook: $context['playbook'],
            context: $context['context'],
            workspaceLabel: $context['workspace_label'],
            playbookVersion: $context['playbook_version'],
        );
    }

    private function contextBuilder(): ReviewAssistantContextBuilder
    {
        return app(ReviewAssistantContextBuilder::class);
    }

    private function refusalGuard(): ReviewAssistantRefusalGuard
    {
        return app(ReviewAssistantRefusalGuard::class);
    }
}
