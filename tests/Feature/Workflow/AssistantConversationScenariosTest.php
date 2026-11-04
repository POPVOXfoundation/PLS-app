<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\AnalysisPage;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Scenario tests for the Nightwatch #7 fix (task 5).
 *
 * All scenarios are simulated: a single PHP process on SQLite in memory, where
 * lockForUpdate() compiles to nothing. Overlaps and in-flight resets are reproduced by
 * running the competing tab's work inside the AI fake closure or inside a one-shot
 * DB::listen callback that fires right after the resolver's lookup select.
 *
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 2.10, 2.11, 2.12, 2.13, 3.3, 3.4, 3.5, 3.6, 3.12**
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

const SCENARIO_RESET_NOTICE = 'This chat was reset in another window. Your message was not saved.';

const SCENARIO_WORKFLOW_REFUSAL_PROMPT = 'Draft findings from this workflow stage.';

const SCENARIO_WORKFLOW_REFUSAL_REPLY = 'I can help with workflow stages and next steps in this tab, but I cannot generate findings, recommendations, or conclusions from the Workflow tab.';

const SCENARIO_RESOLVER_LOOKUP_SQL = '/^select "id" from "agent_conversations" where "pls_review_id" = \? and "user_id" = \? limit 1$/';

const SCENARIO_LOCKED_SELECT_SQL = '/^select "id" from "agent_conversations" where "id" = \? and "pls_review_id" = \? and "user_id" = \? limit 1( for update)?$/';

function scenarioReplyFor(string $prompt): string
{
    return 'Reply to: '.$prompt;
}

function scenarioFakeReplies(): void
{
    ReviewAssistantAgent::fake(fn (string $prompt): string => scenarioReplyFor($prompt));
}

function scenarioMountSidebar(PlsReview $review, string $workspaceKey = 'workflow'): mixed
{
    return Livewire::test(AssistantSidebar::class, [
        'review' => $review->fresh(),
        'workspaceKey' => $workspaceKey,
    ]);
}

/**
 * @return array{0: string, 1: string}
 */
function scenarioExchange(string $prompt, ?string $reply = null): array
{
    return [$prompt, $reply ?? scenarioReplyFor($prompt)];
}

/**
 * @param  list<array{0: string, 1: string}>  $exchanges
 * @return list<array{role: string, content: string}>
 */
function scenarioExpectedMessages(array $exchanges): array
{
    return collect($exchanges)
        ->flatMap(fn (array $exchange): array => [
            ['role' => 'user', 'content' => $exchange[0]],
            ['role' => 'assistant', 'content' => $exchange[1]],
        ])
        ->values()
        ->all();
}

/**
 * @return list<array{role: string, content: string}>
 */
function scenarioStoredMessages(?string $conversationId): array
{
    if ($conversationId === null) {
        return [];
    }

    return DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->orderBy('id')
        ->get(['role', 'content'])
        ->map(fn (object $message): array => ['role' => $message->role, 'content' => $message->content])
        ->all();
}

function scenarioReviewConversationId(PlsReview $review, int $userId): ?string
{
    return DB::table('agent_conversations')
        ->where('pls_review_id', $review->getKey())
        ->where('user_id', $userId)
        ->value('id');
}

function scenarioReviewConversationCount(PlsReview $review, int $userId): int
{
    return DB::table('agent_conversations')
        ->where('pls_review_id', $review->getKey())
        ->where('user_id', $userId)
        ->count();
}

function scenarioMessageCountFor(string $conversationId): int
{
    return DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count();
}

/**
 * Full conversation and message rows, to prove they are untouched.
 *
 * @return array{conversations: list<array<string, mixed>>, messages: list<array<string, mixed>>}
 */
function scenarioTableSnapshot(): array
{
    return [
        'conversations' => DB::table('agent_conversations')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'messages' => DB::table('agent_conversation_messages')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
    ];
}

function scenarioGrantViewAccess(PlsReview $review, User $user, User $invitedBy): void
{
    $review->memberships()->create([
        'user_id' => $user->id,
        'role' => PlsReviewMembershipRole::Viewer,
        'invited_by' => $invitedBy->id,
    ]);
}

function scenarioInsertConversation(PlsReview $review, int $userId, string $title): string
{
    $conversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $userId,
        'pls_review_id' => $review->getKey(),
        'title' => $title,
        'playbook_version' => 'scenario-seed-version',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    return $conversationId;
}

function scenarioInsertMessage(string $conversationId, int $userId, string $role, string $content): void
{
    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'agent' => ReviewAssistantAgent::class,
        'role' => $role,
        'content' => $content,
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function scenarioQueryKind(string $sql): ?string
{
    $sql = trim($sql);

    return match (true) {
        preg_match(SCENARIO_RESOLVER_LOOKUP_SQL, $sql) === 1 => 'resolver_lookup',
        preg_match(SCENARIO_LOCKED_SELECT_SQL, $sql) === 1 => 'locked_select',
        preg_match('/^insert into "agent_conversations"/i', $sql) === 1 => 'conversation_insert',
        preg_match('/^insert into "agent_conversation_messages"/i', $sql) === 1 => 'message_insert',
        default => null,
    };
}

/**
 * Record resolver lookups, locked selects, and inserts into the conversation tables, in order.
 *
 * @return ArrayObject<int, string>
 */
function scenarioQueryRecorder(): ArrayObject
{
    $recorder = new ArrayObject;

    DB::listen(function (QueryExecuted $query) use ($recorder): void {
        $kind = scenarioQueryKind($query->sql);

        if ($kind !== null) {
            $recorder->append($kind);
        }
    });

    return $recorder;
}

/**
 * @param  ArrayObject<int, string>  $recorder
 */
function scenarioCountKind(ArrayObject $recorder, string $kind, int $fromIndex = 0): int
{
    return collect(array_slice($recorder->getArrayCopy(), $fromIndex))->filter(fn (string $recorded): bool => $recorded === $kind)->count();
}

/**
 * Run the callback once, right after the next resolver lookup select returns, once armed.
 *
 * @return ArrayObject<string, bool>
 */
function scenarioOnceAfterResolverLookup(Closure $callback): ArrayObject
{
    $state = new ArrayObject(['armed' => false, 'fired' => false]);

    DB::listen(function (QueryExecuted $query) use ($state, $callback): void {
        if (! $state['armed'] || $state['fired'] || scenarioQueryKind($query->sql) !== 'resolver_lookup') {
            return;
        }

        $state['fired'] = true;
        $callback();
    });

    return $state;
}

/**
 * Invariants that hold after every scenario.
 *
 * @param  array<string, list<array{role: string, content: string}>>  $storedConversations
 * @param  list<string>  $discardedTexts
 */
function scenarioAssertInvariants(array $storedConversations = [], array $discardedTexts = []): void
{
    expect(DB::table('agent_conversations')->whereNull('pls_review_id')->count())
        ->toBe(0, 'unlinked conversation rows')
        ->and(
            DB::table('agent_conversation_messages')
                ->whereNotIn('conversation_id', DB::table('agent_conversations')->select('id'))
                ->count()
        )->toBe(0, 'messages without a conversation row');

    foreach ($storedConversations as $conversationId => $expectedMessages) {
        expect(scenarioStoredMessages($conversationId))->toBe($expectedMessages, "stored messages of {$conversationId}");
    }

    foreach ($discardedTexts as $text) {
        expect(DB::table('agent_conversation_messages')->where('content', 'like', '%'.$text.'%')->count())
            ->toBe(0, "messages containing discarded text: {$text}");
    }
}

function scenarioAgent(): ReviewAssistantAgent
{
    return new ReviewAssistantAgent(
        systemRules: [],
        playbook: [
            'role' => 'Scenario role',
            'intro' => 'Scenario intro',
            'objectives' => [],
            'suggested_prompts' => [],
            'rules' => [],
            'guardrails' => [],
            'allowed_capabilities' => [],
            'disallowed_capabilities' => [],
            'response_style' => [],
        ],
        context: 'Scenario context',
        workspaceLabel: 'Workflow',
        playbookVersion: 'scenario-version',
    );
}

test('stale pages (simulated): a page mounted before the first message retries three times and every exchange lands in one conversation', function () {
    scenarioFakeReplies();

    $review = plsReview(['title' => 'Scenario stale retries']);
    $pageA = scenarioMountSidebar($review);
    $pageB = scenarioMountSidebar($review)->assertSet('assistantConversationId', null);

    $pageA->call('sendPrompt', 'A prompt');

    foreach (range(1, 3) as $retry) {
        $pageB->call('sendPrompt', "B retry {$retry}");
    }

    $conversationId = scenarioReviewConversationId($review, auth()->id());
    $expected = scenarioExpectedMessages([
        scenarioExchange('A prompt'),
        scenarioExchange('B retry 1'),
        scenarioExchange('B retry 2'),
        scenarioExchange('B retry 3'),
    ]);

    expect(scenarioReviewConversationCount($review, auth()->id()))->toBe(1)
        ->and(DB::table('agent_conversations')->count())->toBe(1)
        ->and(scenarioMessageCountFor($conversationId))->toBe(8)
        ->and($pageB->get('assistantConversationId'))->toBe($conversationId)
        ->and($pageB->get('assistantError'))->toBeNull()
        ->and($pageB->get('assistantMessages'))->toEqual($expected);

    scenarioAssertInvariants([$conversationId => $expected]);
});

test('overlapping first prompts (simulated): the sidebar stores the exchange in the winning conversation and keeps its title', function (string $variant) {
    scenarioFakeReplies();

    $review = plsReview(['title' => 'Scenario overlap '.$variant]);
    $userId = auth()->id();
    $page = scenarioMountSidebar($review)->assertSet('assistantConversationId', null);
    $winnerId = null;
    $winnerExchange = scenarioExchange('Winner prompt', 'Winner reply');

    $createWinner = function (bool $withExchange) use (&$winnerId, $review, $userId, $winnerExchange): void {
        $winnerId = scenarioInsertConversation($review, $userId, 'Competitor title');

        if ($withExchange) {
            scenarioInsertMessage($winnerId, $userId, 'user', $winnerExchange[0]);
            scenarioInsertMessage($winnerId, $userId, 'assistant', $winnerExchange[1]);
        }
    };

    $hook = scenarioOnceAfterResolverLookup(fn () => $createWinner($variant === 'race, winner with an exchange'));

    if ($variant === 'winner pre-created with an exchange') {
        $createWinner(true);
    } else {
        $hook['armed'] = true;
    }

    $page->call('sendPrompt', 'B prompt');

    $expected = scenarioExpectedMessages([
        ...($variant === 'race, empty winner' ? [] : [$winnerExchange]),
        scenarioExchange('B prompt'),
    ]);

    expect($hook['fired'])->toBe($variant !== 'winner pre-created with an exchange')
        ->and(scenarioReviewConversationCount($review, $userId))->toBe(1)
        ->and(scenarioReviewConversationId($review, $userId))->toBe($winnerId)
        ->and(DB::table('agent_conversations')->where('id', $winnerId)->value('title'))->toBe('Competitor title')
        ->and($page->get('assistantConversationId'))->toBe($winnerId)
        ->and($page->get('assistantError'))->toBeNull()
        ->and($page->get('assistantMessages'))->toEqual($expected);

    scenarioAssertInvariants([$winnerId => $expected]);
})->with([
    'race, empty winner',
    'race, winner with an exchange',
    'winner pre-created with an exchange',
]);

test('agent history without a participant (simulated): withConversationHistory loads the latest 20 of 25 messages oldest first', function () {
    $review = plsReview(['title' => 'Scenario agent history']);
    $userId = auth()->id();
    $conversationId = scenarioInsertConversation($review, $userId, 'History conversation');

    foreach (range(1, 25) as $index) {
        scenarioInsertMessage($conversationId, $userId, $index % 2 === 1 ? 'user' : 'assistant', "History message {$index}");
    }

    $agent = scenarioAgent()->withConversationHistory($conversationId);

    $history = collect($agent->messages())
        ->map(fn (Message $message): array => ['role' => $message->role->value, 'content' => (string) $message->content])
        ->all();

    $expected = collect(range(6, 25))
        ->map(fn (int $index): array => [
            'role' => $index % 2 === 1 ? 'user' : 'assistant',
            'content' => "History message {$index}",
        ])
        ->all();

    expect($agent->hasConversationParticipant())->toBeFalse()
        ->and($agent->conversationParticipant())->toBeNull()
        ->and($agent->currentConversation())->toBe($conversationId)
        ->and($history)->toHaveCount(20)
        ->and($history)->toBe($expected);

    scenarioAssertInvariants();
});

test('no SDK writes (simulated): stale and fresh pages insert only the resolver row and two messages per send after the locked select', function () {
    $review = plsReview(['title' => 'Scenario no SDK writes']);
    $recorder = null;

    ReviewAssistantAgent::fake(function (string $prompt) use (&$recorder): string {
        $recorder?->append('provider_call');

        return scenarioReplyFor($prompt);
    });

    $pageA = scenarioMountSidebar($review)->assertSet('assistantConversationId', null);
    $pageB = scenarioMountSidebar($review)->assertSet('assistantConversationId', null);

    $recorder = scenarioQueryRecorder();

    $pageA->call('sendPrompt', 'A first');
    $pageB->call('sendPrompt', 'B first');
    $pageB->call('sendPrompt', 'B second');

    $pageC = scenarioMountSidebar($review);
    $pageC->call('sendPrompt', 'C first');
    $pageA->call('sendPrompt', 'A second');

    $storedSend = ['resolver_lookup', 'provider_call', 'locked_select', 'message_insert', 'message_insert'];

    expect($recorder->getArrayCopy())->toBe([
        'resolver_lookup', 'conversation_insert', 'provider_call', 'locked_select', 'message_insert', 'message_insert',
        ...$storedSend,
        ...$storedSend,
        ...$storedSend,
        ...$storedSend,
    ])
        ->and(scenarioCountKind($recorder, 'conversation_insert'))->toBe(1)
        ->and(scenarioCountKind($recorder, 'message_insert'))->toBe(10);

    $conversationId = scenarioReviewConversationId($review, auth()->id());

    scenarioAssertInvariants([
        $conversationId => scenarioExpectedMessages([
            scenarioExchange('A first'),
            scenarioExchange('B first'),
            scenarioExchange('B second'),
            scenarioExchange('C first'),
            scenarioExchange('A second'),
        ]),
    ]);
});

test('server-side resolution (simulated): a send writes only to the conversation resolved from the review and user', function (string $path, string $setup) {
    scenarioFakeReplies();

    $exchange = $path === 'refusal'
        ? scenarioExchange(SCENARIO_WORKFLOW_REFUSAL_PROMPT, SCENARIO_WORKFLOW_REFUSAL_REPLY)
        : scenarioExchange('B prompt');
    $seedExchange = $path === 'refusal'
        ? scenarioExchange(SCENARIO_WORKFLOW_REFUSAL_PROMPT, SCENARIO_WORKFLOW_REFUSAL_REPLY)
        : scenarioExchange('Seed prompt');

    $review = plsReview(['title' => "Scenario server-side {$path} {$setup}"]);
    $userId = auth()->id();
    $heldId = null;

    if ($setup === 'held deleted id') {
        scenarioMountSidebar($review)->call('sendPrompt', $seedExchange[0]);
        $heldId = scenarioReviewConversationId($review, $userId);

        $pageA = scenarioMountSidebar($review)->assertSet('assistantConversationId', $heldId);
        $pageB = scenarioMountSidebar($review)->assertSet('assistantConversationId', $heldId);

        $pageA->call('resetAssistantConversation');
    } else {
        $pageA = scenarioMountSidebar($review);
        $pageB = scenarioMountSidebar($review)->assertSet('assistantConversationId', null);

        $pageA->call('sendPrompt', $seedExchange[0]);
    }

    $pageB->call('sendPrompt', $exchange[0]);

    $resolvedId = scenarioReviewConversationId($review, $userId);
    $expected = scenarioExpectedMessages($setup === 'held deleted id' ? [$exchange] : [$seedExchange, $exchange]);

    expect($resolvedId)->not->toBeNull()
        ->and(scenarioReviewConversationCount($review, $userId))->toBe(1)
        ->and($pageB->get('assistantConversationId'))->toBe($resolvedId)
        ->and($pageB->get('assistantError'))->toBeNull()
        ->and($pageB->get('assistantMessages'))->toEqual($expected);

    if ($heldId !== null) {
        expect($resolvedId)->not->toBe($heldId)
            ->and(scenarioMessageCountFor($heldId))->toBe(0);
    }

    if ($path === 'refusal') {
        ReviewAssistantAgent::assertNeverPrompted();
    }

    scenarioAssertInvariants([$resolvedId => $expected]);
})->with([
    'ai path' => ['ai'],
    'refusal path' => ['refusal'],
])->with([
    'held deleted id',
    'held null while the conversation exists',
]);

test('re-authorization on send (simulated): a send after view access is revoked is forbidden and writes nothing', function (bool $hasConversation) {
    scenarioFakeReplies();

    $owner = auth()->user();
    $member = User::factory()->create();
    $review = plsReview(['title' => 'Scenario re-authorization', 'created_by' => $owner->id]);
    scenarioGrantViewAccess($review, $member, $owner);

    $this->actingAs($member);
    $page = scenarioMountSidebar($review);

    if ($hasConversation) {
        $page->call('sendPrompt', 'Before revoke');
    }

    $before = scenarioTableSnapshot();

    DB::table('pls_review_memberships')
        ->where('pls_review_id', $review->id)
        ->where('user_id', $member->id)
        ->delete();

    $page->call('sendPrompt', 'After revoke')->assertForbidden();

    ReviewAssistantAgent::assertNotPrompted('After revoke');

    expect(scenarioTableSnapshot())->toEqual($before)
        ->and(scenarioReviewConversationCount($review, $member->id))->toBe($hasConversation ? 1 : 0);

    scenarioAssertInvariants(
        $hasConversation ? [scenarioReviewConversationId($review, $member->id) => scenarioExpectedMessages([scenarioExchange('Before revoke')])] : [],
        ['After revoke'],
    );
})->with([
    'with an existing conversation' => [true],
    'before any conversation' => [false],
]);

test('titles (simulated): a first send titles the new row with Str::limit of the prompt at 100 characters', function () {
    scenarioFakeReplies();

    $prompt = Str::of('Summarize the current step and ')->append(str_repeat('details', 30))->substr(0, 150)->toString();
    $review = plsReview(['title' => 'Scenario long title']);

    expect(strlen($prompt))->toBe(150);

    scenarioMountSidebar($review)->call('sendPrompt', $prompt)->assertSet('assistantError', null);

    $conversationId = scenarioReviewConversationId($review, auth()->id());

    expect(DB::table('agent_conversations')->where('id', $conversationId)->value('title'))
        ->toBe(Str::limit($prompt, 100))
        ->toHaveLength(103);

    scenarioAssertInvariants([$conversationId => scenarioExpectedMessages([scenarioExchange($prompt)])]);
});

test('titles (simulated): sends to an existing conversation leave its title unchanged', function () {
    scenarioFakeReplies();

    $review = plsReview(['title' => 'Scenario original title']);
    $userId = auth()->id();
    $stalePage = scenarioMountSidebar($review)->assertSet('assistantConversationId', null);
    $conversationId = scenarioInsertConversation($review, $userId, 'Original title');
    $currentPage = scenarioMountSidebar($review)->assertSet('assistantConversationId', $conversationId);

    $currentPage->call('sendPrompt', 'Current page prompt');
    $stalePage->call('sendPrompt', 'Stale page prompt');

    expect(DB::table('agent_conversations')->where('id', $conversationId)->value('title'))->toBe('Original title')
        ->and(scenarioReviewConversationCount($review, $userId))->toBe(1);

    scenarioAssertInvariants([
        $conversationId => scenarioExpectedMessages([
            scenarioExchange('Current page prompt'),
            scenarioExchange('Stale page prompt'),
        ]),
    ]);
});

test('provider call count (simulated): N AI sends from stale and fresh pages make N calls and refusal sends make none', function () {
    $providerCalls = 0;

    ReviewAssistantAgent::fake(function (string $prompt) use (&$providerCalls): string {
        $providerCalls++;

        return scenarioReplyFor($prompt);
    });

    $review = plsReview(['title' => 'Scenario provider calls']);
    $pageA = scenarioMountSidebar($review);
    $pageB = scenarioMountSidebar($review);

    $pageA->call('sendPrompt', 'A first');
    $pageB->call('sendPrompt', 'B first');
    $pageB->call('sendPrompt', 'B second');
    scenarioMountSidebar($review)->call('sendPrompt', 'C first');

    expect($providerCalls)->toBe(4);

    $refusalReview = plsReview(['title' => 'Scenario provider calls refusal']);
    $refusalPageA = scenarioMountSidebar($refusalReview);
    $refusalPageB = scenarioMountSidebar($refusalReview);

    $refusalPageA->call('sendPrompt', SCENARIO_WORKFLOW_REFUSAL_PROMPT);
    $refusalPageB->call('sendPrompt', SCENARIO_WORKFLOW_REFUSAL_PROMPT);
    $pageA->call('sendPrompt', SCENARIO_WORKFLOW_REFUSAL_PROMPT);

    expect($providerCalls)->toBe(4);

    $refusalExchange = scenarioExchange(SCENARIO_WORKFLOW_REFUSAL_PROMPT, SCENARIO_WORKFLOW_REFUSAL_REPLY);

    scenarioAssertInvariants([
        scenarioReviewConversationId($review, auth()->id()) => scenarioExpectedMessages([
            scenarioExchange('A first'),
            scenarioExchange('B first'),
            scenarioExchange('B second'),
            scenarioExchange('C first'),
            $refusalExchange,
        ]),
        scenarioReviewConversationId($refusalReview, auth()->id()) => scenarioExpectedMessages([$refusalExchange, $refusalExchange]),
    ]);
});

test('in-flight reset, AI path, with a hand-off (simulated): the exchange is discarded and the workspace is told it failed', function (string $workspaceKey, string $prompt, bool $provisionalFindings, bool $reportOutline, string $workspacePage, string $failedEvent, string $generatedEvent) {
    Exceptions::fake();

    $review = plsReview(['title' => "Scenario in-flight hand-off {$workspaceKey}"]);
    $recorder = scenarioQueryRecorder();
    $pageA = null;
    $hasReset = false;
    $insertCountAfterReset = null;

    ReviewAssistantAgent::fake(function (string $receivedPrompt) use (&$pageA, &$hasReset, &$insertCountAfterReset, $prompt, $recorder): string {
        if ($receivedPrompt === $prompt && ! $hasReset) {
            $hasReset = true;

            // Variant: nested Livewire test call on tab A inside tab B's provider call.
            $pageA->call('resetAssistantConversation');

            $insertCountAfterReset = $recorder->count();

            return 'B in-flight reply';
        }

        return scenarioReplyFor($receivedPrompt);
    });

    $pageA = scenarioMountSidebar($review);
    $pageA->call('sendPrompt', 'Seed prompt');
    $deletedId = scenarioReviewConversationId($review, auth()->id());

    $pageB = scenarioMountSidebar($review, $workspaceKey)->assertSet('assistantConversationId', $deletedId);

    $pageB->call('sendRequestedPrompt', $prompt, $provisionalFindings, $reportOutline)
        ->assertDispatchedTo($workspacePage, $failedEvent)
        ->assertNotDispatched($generatedEvent);

    expect($hasReset)->toBeTrue()
        ->and(scenarioCountKind($recorder, 'message_insert', $insertCountAfterReset))->toBe(0)
        ->and(scenarioReviewConversationCount($review, auth()->id()))->toBe(0)
        ->and(scenarioMessageCountFor($deletedId))->toBe(0)
        ->and($pageB->get('assistantError'))->toBe(SCENARIO_RESET_NOTICE)
        ->and($pageB->get('assistantMessages'))->toBe([])
        ->and($pageB->get('assistantConversationId'))->toBeNull();

    Exceptions::assertNothingReported();

    scenarioAssertInvariants([], [$prompt, 'B in-flight reply']);
})->with([
    'analysis provisional findings' => ['analysis', 'Suggest provisional findings', true, false, AnalysisPage::class, 'provisional-findings-failed', 'provisional-findings-generated'],
    'reports outline' => ['reports', 'Prepare a provisional PLS report outline from the saved review record.', false, true, ReportsPage::class, 'report-outline-failed', 'report-outline-generated'],
]);

test('in-flight reset, refusal path (simulated): a reset right after the resolver lookup discards the synthetic exchange', function () {
    Exceptions::fake();

    $providerCalls = 0;

    ReviewAssistantAgent::fake(function (string $prompt) use (&$providerCalls): string {
        $providerCalls++;

        return scenarioReplyFor($prompt);
    });

    $review = plsReview(['title' => 'Scenario in-flight refusal reset']);
    $pageA = scenarioMountSidebar($review);
    $pageA->call('sendPrompt', 'Seed prompt');
    $deletedId = scenarioReviewConversationId($review, auth()->id());

    $pageB = scenarioMountSidebar($review)->assertSet('assistantConversationId', $deletedId);

    $recorder = scenarioQueryRecorder();
    $insertCountAfterReset = null;

    $hook = scenarioOnceAfterResolverLookup(function () use ($pageA, $recorder, &$insertCountAfterReset): void {
        // Variant: nested Livewire test call on tab A after tab B's resolver lookup.
        $pageA->call('resetAssistantConversation');

        $insertCountAfterReset = $recorder->count();
    });
    $hook['armed'] = true;
    $providerCallsBeforeSend = $providerCalls;

    $pageB->call('sendPrompt', SCENARIO_WORKFLOW_REFUSAL_PROMPT);

    expect($hook['fired'])->toBeTrue()
        ->and($providerCalls)->toBe($providerCallsBeforeSend)
        ->and(scenarioCountKind($recorder, 'message_insert', $insertCountAfterReset))->toBe(0)
        ->and(scenarioReviewConversationCount($review, auth()->id()))->toBe(0)
        ->and(scenarioMessageCountFor($deletedId))->toBe(0)
        ->and($pageB->get('assistantError'))->toBe(SCENARIO_RESET_NOTICE)
        ->and($pageB->get('assistantMessages'))->toBe([])
        ->and($pageB->get('assistantConversationId'))->toBeNull();

    Exceptions::assertNothingReported();

    scenarioAssertInvariants([], [SCENARIO_WORKFLOW_REFUSAL_PROMPT, SCENARIO_WORKFLOW_REFUSAL_REPLY]);
});

test('in-flight reset, then A sends again, refusal path (simulated): the new conversation keeps exactly A\'s messages and B writes nothing', function () {
    Exceptions::fake();
    scenarioFakeReplies();

    $review = plsReview(['title' => 'Scenario in-flight refusal reset and resend']);
    $userId = auth()->id();
    $pageA = scenarioMountSidebar($review);
    $pageA->call('sendPrompt', 'Seed prompt');
    $deletedId = scenarioReviewConversationId($review, $userId);

    $pageB = scenarioMountSidebar($review)->assertSet('assistantConversationId', $deletedId);

    $recorder = scenarioQueryRecorder();
    $newConversationId = null;
    $newConversationSnapshot = null;
    $insertCountAfterTabA = null;

    $hook = scenarioOnceAfterResolverLookup(function () use ($pageA, $review, $userId, $recorder, &$newConversationId, &$newConversationSnapshot, &$insertCountAfterTabA): void {
        // Variant: nested Livewire test calls on tab A after tab B's resolver lookup.
        $pageA->call('resetAssistantConversation');
        $pageA->call('sendPrompt', 'A prompt');

        $newConversationId = scenarioReviewConversationId($review, $userId);
        $newConversationSnapshot = scenarioStoredMessages($newConversationId);
        $insertCountAfterTabA = $recorder->count();
    });
    $hook['armed'] = true;

    $pageB->call('sendPrompt', SCENARIO_WORKFLOW_REFUSAL_PROMPT);

    $tabAMessages = scenarioExpectedMessages([scenarioExchange('A prompt')]);

    expect($hook['fired'])->toBeTrue()
        ->and($newConversationId)->not->toBeNull()
        ->and($newConversationId)->not->toBe($deletedId)
        ->and($newConversationSnapshot)->toBe($tabAMessages)
        ->and(scenarioCountKind($recorder, 'message_insert', $insertCountAfterTabA))->toBe(0)
        ->and(DB::table('agent_conversations')->where('pls_review_id', $review->id)->where('user_id', $userId)->pluck('id')->all())->toBe([$newConversationId])
        ->and(scenarioMessageCountFor($deletedId))->toBe(0)
        ->and($pageB->get('assistantError'))->toBe(SCENARIO_RESET_NOTICE)
        ->and($pageB->get('assistantConversationId'))->toBe($newConversationId)
        ->and($pageB->get('assistantMessages'))->toEqual($tabAMessages);

    Exceptions::assertNothingReported();

    scenarioAssertInvariants(
        [$newConversationId => $tabAMessages],
        [SCENARIO_WORKFLOW_REFUSAL_PROMPT, SCENARIO_WORKFLOW_REFUSAL_REPLY],
    );
});

test('isolation between users (simulated): stale pages of two users give two conversations that each show only their own messages', function () {
    scenarioFakeReplies();

    $userOne = auth()->user();
    $userTwo = User::factory()->create();
    $review = plsReview(['title' => 'Scenario user isolation', 'created_by' => $userOne->id]);
    scenarioGrantViewAccess($review, $userTwo, $userOne);

    $pages = [];

    foreach (['one' => $userOne, 'two' => $userTwo] as $label => $user) {
        $this->actingAs($user);
        $pages[$label] = [
            'user' => $user,
            'a' => scenarioMountSidebar($review)->assertSet('assistantConversationId', null),
            'b' => scenarioMountSidebar($review)->assertSet('assistantConversationId', null),
        ];
    }

    foreach (['a', 'b'] as $tab) {
        foreach ($pages as $label => $set) {
            $this->actingAs($set['user']);
            $set[$tab]->call('sendPrompt', "User {$label} tab {$tab}");
        }
    }

    $stored = [];

    foreach ($pages as $label => $set) {
        $conversationId = scenarioReviewConversationId($review, $set['user']->id);
        $expected = scenarioExpectedMessages([
            scenarioExchange("User {$label} tab a"),
            scenarioExchange("User {$label} tab b"),
        ]);

        $this->actingAs($set['user']);

        expect(scenarioReviewConversationCount($review, $set['user']->id))->toBe(1, "user {$label}")
            ->and($set['b']->get('assistantConversationId'))->toBe($conversationId, "user {$label}")
            ->and($set['b']->get('assistantMessages'))->toEqual($expected, "user {$label}")
            ->and(scenarioMountSidebar($review)->get('assistantMessages'))->toEqual($expected, "user {$label}");

        $stored[$conversationId] = $expected;
    }

    expect(DB::table('agent_conversations')->where('pls_review_id', $review->id)->count())->toBe(2);

    scenarioAssertInvariants($stored);
});

test('isolation between users (simulated): one user\'s reset does not discard the other user\'s in-flight send', function () {
    Exceptions::fake();

    $userOne = auth()->user();
    $userTwo = User::factory()->create();
    $review = plsReview(['title' => 'Scenario user isolation reset', 'created_by' => $userOne->id]);
    scenarioGrantViewAccess($review, $userTwo, $userOne);

    $pageUserOne = null;
    $hasReset = false;

    ReviewAssistantAgent::fake(function (string $prompt) use (&$pageUserOne, &$hasReset, $userOne, $userTwo): string {
        if ($prompt === 'User two in-flight prompt' && ! $hasReset) {
            $hasReset = true;

            // Variant: nested Livewire test call as user one inside user two's provider call.
            $this->actingAs($userOne);
            $pageUserOne->call('resetAssistantConversation');
            $this->actingAs($userTwo);
        }

        return scenarioReplyFor($prompt);
    });

    $pageUserOne = scenarioMountSidebar($review);
    $pageUserOne->call('sendPrompt', 'User one seed');
    $userOneConversationId = scenarioReviewConversationId($review, $userOne->id);

    $this->actingAs($userTwo);
    $pageUserTwo = scenarioMountSidebar($review);
    $pageUserTwo->call('sendPrompt', 'User two seed');
    $userTwoConversationId = scenarioReviewConversationId($review, $userTwo->id);

    $pageUserTwo->call('sendPrompt', 'User two in-flight prompt');

    $expected = scenarioExpectedMessages([
        scenarioExchange('User two seed'),
        scenarioExchange('User two in-flight prompt'),
    ]);

    expect($hasReset)->toBeTrue()
        ->and(scenarioReviewConversationCount($review, $userOne->id))->toBe(0)
        ->and(scenarioMessageCountFor($userOneConversationId))->toBe(0)
        ->and(scenarioReviewConversationId($review, $userTwo->id))->toBe($userTwoConversationId)
        ->and($pageUserTwo->get('assistantError'))->toBeNull()
        ->and($pageUserTwo->get('assistantConversationId'))->toBe($userTwoConversationId)
        ->and($pageUserTwo->get('assistantMessages'))->toEqual($expected);

    Exceptions::assertNothingReported();

    scenarioAssertInvariants([$userTwoConversationId => $expected], ['User one seed']);
});

test('isolation between reviews (simulated): stale pages on two reviews give two conversations with no cross-writes', function () {
    scenarioFakeReplies();

    $reviews = [
        'one' => plsReview(['title' => 'Scenario review isolation one']),
        'two' => plsReview(['title' => 'Scenario review isolation two']),
    ];
    $pages = collect($reviews)->map(fn (PlsReview $review): array => [
        'a' => scenarioMountSidebar($review)->assertSet('assistantConversationId', null),
        'b' => scenarioMountSidebar($review)->assertSet('assistantConversationId', null),
    ])->all();

    foreach (['a', 'b'] as $tab) {
        foreach ($pages as $label => $set) {
            $set[$tab]->call('sendPrompt', "Review {$label} tab {$tab}");
        }
    }

    $stored = [];

    foreach ($reviews as $label => $review) {
        $conversationId = scenarioReviewConversationId($review, auth()->id());
        $expected = scenarioExpectedMessages([
            scenarioExchange("Review {$label} tab a"),
            scenarioExchange("Review {$label} tab b"),
        ]);

        expect(scenarioReviewConversationCount($review, auth()->id()))->toBe(1, "review {$label}")
            ->and($pages[$label]['b']->get('assistantConversationId'))->toBe($conversationId, "review {$label}")
            ->and($pages[$label]['b']->get('assistantMessages'))->toEqual($expected, "review {$label}");

        $stored[$conversationId] = $expected;
    }

    expect(DB::table('agent_conversations')->count())->toBe(2);

    scenarioAssertInvariants($stored);
});

test('second tab after reset (simulated): the exchange lands in the review conversation and nothing is written to the deleted id', function (bool $tabASendsFirst) {
    scenarioFakeReplies();

    $review = plsReview(['title' => 'Scenario second tab after reset']);
    $userId = auth()->id();

    scenarioMountSidebar($review)->call('sendPrompt', 'Seed prompt');
    $deletedId = scenarioReviewConversationId($review, $userId);

    $pageA = scenarioMountSidebar($review)->assertSet('assistantConversationId', $deletedId);
    $pageB = scenarioMountSidebar($review)->assertSet('assistantConversationId', $deletedId);

    $pageA->call('resetAssistantConversation');

    $exchanges = [];

    if ($tabASendsFirst) {
        $pageA->call('sendPrompt', 'A prompt');
        $exchanges[] = scenarioExchange('A prompt');
    }

    $newConversationId = scenarioReviewConversationId($review, $userId);

    $pageB->call('sendPrompt', 'B prompt');
    $exchanges[] = scenarioExchange('B prompt');

    $resolvedId = scenarioReviewConversationId($review, $userId);
    $expected = scenarioExpectedMessages($exchanges);

    expect($resolvedId)->not->toBe($deletedId)
        ->and(scenarioReviewConversationCount($review, $userId))->toBe(1)
        ->and(scenarioMessageCountFor($deletedId))->toBe(0)
        ->and($pageB->get('assistantConversationId'))->toBe($resolvedId)
        ->and($pageB->get('assistantError'))->toBeNull()
        ->and($pageB->get('assistantMessages'))->toEqual($expected);

    if ($tabASendsFirst) {
        expect($resolvedId)->toBe($newConversationId);
    }

    scenarioAssertInvariants([$resolvedId => $expected]);
})->with([
    'A resets, B sends' => [false],
    'A resets, A sends, B sends' => [true],
]);

test('authorization preserved (simulated): an unauthorized user gets 403 at mount and no conversation is created', function () {
    ReviewAssistantAgent::fake();

    $owner = auth()->user();
    $review = plsReview(['title' => 'Scenario unauthorized mount', 'created_by' => $owner->id]);
    $outsider = User::factory()->create();

    $this->actingAs($outsider);

    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'workflow'])
        ->assertForbidden();

    ReviewAssistantAgent::assertNeverPrompted();

    expect(DB::table('agent_conversations')->count())->toBe(0)
        ->and(DB::table('agent_conversation_messages')->count())->toBe(0);

    scenarioAssertInvariants();
});

test('authorization preserved (simulated): the client cannot point the sidebar at another user\'s conversation', function () {
    scenarioFakeReplies();

    $owner = auth()->user();
    $otherUser = User::factory()->create();
    $review = plsReview(['title' => 'Scenario locked conversation id', 'created_by' => $owner->id]);
    scenarioGrantViewAccess($review, $otherUser, $owner);

    $this->actingAs($otherUser);
    scenarioMountSidebar($review)->call('sendPrompt', 'Other user prompt');
    $otherConversationId = scenarioReviewConversationId($review, $otherUser->id);
    $otherConversation = (array) DB::table('agent_conversations')->where('id', $otherConversationId)->first();
    $otherMessages = DB::table('agent_conversation_messages')->where('conversation_id', $otherConversationId)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();

    $this->actingAs($owner);
    $page = scenarioMountSidebar($review);

    expect(fn () => $page->set('assistantConversationId', $otherConversationId))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $page->call('sendPrompt', 'Owner prompt');

    $ownerConversationId = scenarioReviewConversationId($review, $owner->id);

    expect($ownerConversationId)->not->toBe($otherConversationId)
        ->and($page->get('assistantConversationId'))->toBe($ownerConversationId)
        ->and((array) DB::table('agent_conversations')->where('id', $otherConversationId)->first())->toBe($otherConversation)
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $otherConversationId)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all())->toBe($otherMessages);

    scenarioAssertInvariants([
        $otherConversationId => scenarioExpectedMessages([scenarioExchange('Other user prompt')]),
        $ownerConversationId => scenarioExpectedMessages([scenarioExchange('Owner prompt')]),
    ]);
});
