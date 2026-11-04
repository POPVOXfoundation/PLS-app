<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\AnalysisPage;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Storage\DatabaseConversationStore;
use Livewire\Livewire;

/**
 * Storage failures during an assistant send (review finding 1 on commit 4d2c598).
 *
 * Property 3: Bug Condition - Storage failures are reported inline and leave no partial exchange.
 *
 * A database error in the conversation resolver or in the locked message write must be
 * reported, shown inline, roll back the whole exchange, and tell a waiting Analysis or
 * Reports workspace that its request failed.
 *
 * **Validates: Requirements 1.11, 2.14, 2.15**
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

const STORAGE_FAILURE_ERROR = 'The assistant could not save this message. Try again.';

const STORAGE_FAILURE_ASSISTANT_INSERT_MESSAGE = 'Simulated assistant message insert failure';

const STORAGE_FAILURE_RESOLVER_MESSAGE = 'Simulated conversation resolver failure';

const STORAGE_FAILURE_RESOLVER_LOOKUP_SQL = 'select "id" from "agent_conversations" where "pls_review_id"';

const STORAGE_FAILURE_RESOLVER_INSERT_SQL = 'insert into "agent_conversations"';

const STORAGE_FAILURE_ANALYSIS_REPLY = <<<'TEXT'
POTENTIAL FINDING:
Title: Reporting remains incomplete
Type: implementation gap
Draft: The available evidence suggests reporting is incomplete.
Supporting record: Annual report: "Reporting was delayed."
Limitations: Agency responses have not been recorded.
Reviewer question: Has the missing report now been published?
END FINDING
TEXT;

const STORAGE_FAILURE_REPORT_REPLY = <<<'TEXT'
REPORT OUTLINE:
Report title: Draft report on implementation
SECTION:
Title: Purpose and scope
Purpose: Set out the legislation and questions under review.
Draw on: Review overview and linked legislation.
Limitations: Confirm the final scope with the review team.
END SECTION
END OUTLINE
TEXT;

/**
 * Sends that hand off to the Analysis or Reports workspace, on the AI path and the refusal path.
 *
 * Refusal prompts are refused by ReviewAssistantRefusalGuard on a fresh review: 'final
 * recommendation' on analysis, and 'final report' on reports with no published report.
 *
 * @return array<string, array{workspaceKey: string, prompt: string, reply: string, refused: bool, handoff: 'provisionalFindings'|'reportOutline'}>
 */
function storageFailureCases(): array
{
    return [
        'analysis AI path' => [
            'workspaceKey' => 'analysis',
            'prompt' => 'Suggest provisional findings from the saved review record.',
            'reply' => STORAGE_FAILURE_ANALYSIS_REPLY,
            'refused' => false,
            'handoff' => 'provisionalFindings',
        ],
        'reports AI path' => [
            'workspaceKey' => 'reports',
            'prompt' => 'Prepare a provisional PLS report outline from the saved review record.',
            'reply' => STORAGE_FAILURE_REPORT_REPLY,
            'refused' => false,
            'handoff' => 'reportOutline',
        ],
        'analysis refusal path' => [
            'workspaceKey' => 'analysis',
            'prompt' => 'Give me the final recommendation',
            'reply' => 'I can help with provisional findings, themes, and recommendation options in this tab, but I cannot present them as final conclusions.',
            'refused' => true,
            'handoff' => 'provisionalFindings',
        ],
        'reports refusal path' => [
            'workspaceKey' => 'reports',
            'prompt' => 'Write the final report',
            'reply' => 'I can help with report structure and provisional drafting here, but I cannot treat this as final publication language until the report record shows a published outcome.',
            'refused' => true,
            'handoff' => 'reportOutline',
        ],
    ];
}

/**
 * @return array<string, array{0: array{workspaceKey: string, prompt: string, reply: string, refused: bool, handoff: 'provisionalFindings'|'reportOutline'}}>
 */
function storageFailureCaseDataset(string ...$names): array
{
    return collect(storageFailureCases())
        ->only($names === [] ? array_keys(storageFailureCases()) : $names)
        ->map(fn (array $case): array => [$case])
        ->all();
}

function storageFailureMountSidebar(PlsReview $review, string $workspaceKey): mixed
{
    return Livewire::test(AssistantSidebar::class, [
        'review' => $review->fresh(),
        'workspaceKey' => $workspaceKey,
    ]);
}

/**
 * Fake the agent so AI-path sends get the case's reply. Refusal-path sends never prompt it.
 *
 * @param  array{reply: string, refused: bool}  $case
 */
function storageFailureFakeAgent(array $case): void
{
    if ($case['refused']) {
        ReviewAssistantAgent::fake();

        return;
    }

    ReviewAssistantAgent::fake([$case['reply']]);
}

/**
 * Bind a ConversationStore that delegates to the real database store and throws from
 * storeAssistantMessage() after the real storeUserMessage() has inserted the user message.
 */
function storageFailureBindFailingAssistantInsertStore(): object
{
    $store = new class(new DatabaseConversationStore) implements ConversationStore
    {
        public int $userMessagesStored = 0;

        public int $assistantInsertFailures = 0;

        public function __construct(private DatabaseConversationStore $store) {}

        public function latestConversationId(string|int $userId): ?string
        {
            return $this->store->latestConversationId($userId);
        }

        public function storeConversation(string|int|null $userId, string $title): string
        {
            return $this->store->storeConversation($userId, $title);
        }

        public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
        {
            $messageId = $this->store->storeUserMessage($conversationId, $userId, $prompt);
            $this->userMessagesStored++;

            return $messageId;
        }

        public function storeAssistantMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt, AgentResponse $response): string
        {
            $this->assistantInsertFailures++;

            throw new RuntimeException(STORAGE_FAILURE_ASSISTANT_INSERT_MESSAGE);
        }

        public function getLatestConversationMessages(string $conversationId, int $limit): Collection
        {
            return $this->store->getLatestConversationMessages($conversationId, $limit);
        }
    };

    app()->instance(ConversationStore::class, $store);

    return $store;
}

/**
 * Arm a one-shot hook that throws before the first statement starting with the given SQL.
 *
 * @return ArrayObject<string, bool>
 */
function storageFailureFailNextStatement(string $sqlPrefix): ArrayObject
{
    $state = new ArrayObject(['fired' => false]);

    DB::beforeExecuting(function (string $query) use ($sqlPrefix, $state): void {
        if ($state['fired'] || ! str_starts_with($query, $sqlPrefix)) {
            return;
        }

        $state['fired'] = true;

        throw new RuntimeException(STORAGE_FAILURE_RESOLVER_MESSAGE);
    });

    return $state;
}

/**
 * @param  array{workspaceKey: string, prompt: string, reply: string, refused: bool, handoff: 'provisionalFindings'|'reportOutline'}  $case
 */
function storageFailureSend(mixed $sidebar, array $case): mixed
{
    return $sidebar->call(
        'sendRequestedPrompt',
        $case['prompt'],
        provisionalFindings: $case['handoff'] === 'provisionalFindings',
        reportOutline: $case['handoff'] === 'reportOutline',
    );
}

/**
 * Assert the inline storage-failure handling shared by every case.
 *
 * @param  array{workspaceKey: string, prompt: string, reply: string, refused: bool, handoff: 'provisionalFindings'|'reportOutline'}  $case
 */
function storageFailureAssertHandled(mixed $sidebar, array $case, ?string $conversationIdBeforeSend): void
{
    $sidebar
        ->assertSet('assistantError', STORAGE_FAILURE_ERROR)
        ->assertSet('assistantConversationId', $conversationIdBeforeSend)
        ->assertDispatched('assistant-message-added');

    if ($case['handoff'] === 'provisionalFindings') {
        $sidebar
            ->assertDispatchedTo(AnalysisPage::class, 'provisional-findings-failed')
            ->assertNotDispatched('provisional-findings-generated');
    } else {
        $sidebar
            ->assertDispatchedTo(ReportsPage::class, 'report-outline-failed')
            ->assertNotDispatched('report-outline-generated');
    }

    Exceptions::assertReported(RuntimeException::class);

    expect(collect($sidebar->get('assistantMessages'))->last())->toBe([
        'role' => 'user',
        'content' => $case['prompt'],
    ])
        ->and(DB::table('agent_conversation_messages')->whereIn('content', [$case['prompt'], $case['reply']])->count())->toBe(0)
        ->and(DB::table('agent_conversation_messages')->count())->toBe(0);
}

test('a failed assistant message insert rolls back the user message and is handled inline', function (array $case) {
    Exceptions::fake();
    storageFailureFakeAgent($case);

    $review = plsReview(['title' => 'Storage failure: '.$case['prompt']]);
    $sidebar = storageFailureMountSidebar($review, $case['workspaceKey']);
    $conversationIdBeforeSend = $sidebar->get('assistantConversationId');

    $store = storageFailureBindFailingAssistantInsertStore();

    storageFailureSend($sidebar, $case);

    expect($store->userMessagesStored)->toBe(1)
        ->and($store->assistantInsertFailures)->toBe(1);

    storageFailureAssertHandled($sidebar, $case, $conversationIdBeforeSend);
})->with(storageFailureCaseDataset());

test('a failed conversation lookup or insert in the resolver is handled inline without prompting the agent', function (string $sqlPrefix, array $case) {
    Exceptions::fake();
    ReviewAssistantAgent::fake([$case['reply']]);

    $review = plsReview(['title' => 'Resolver failure: '.$case['prompt']]);
    $sidebar = storageFailureMountSidebar($review, $case['workspaceKey']);
    $conversationIdBeforeSend = $sidebar->get('assistantConversationId');

    $hook = storageFailureFailNextStatement($sqlPrefix);

    storageFailureSend($sidebar, $case);

    expect($hook['fired'])->toBeTrue();

    storageFailureAssertHandled($sidebar, $case, $conversationIdBeforeSend);

    ReviewAssistantAgent::assertNeverPrompted();
})->with([
    'lookup' => [STORAGE_FAILURE_RESOLVER_LOOKUP_SQL],
    'insert' => [STORAGE_FAILURE_RESOLVER_INSERT_SQL],
])->with(storageFailureCaseDataset('analysis AI path', 'reports refusal path'));
