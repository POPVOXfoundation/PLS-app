<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\AnalysisPage;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Models\User;
use App\Support\PlsAssistant\PlaybookRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;

/**
 * Preservation baseline for Nightwatch #7, observed on unfixed code.
 *
 * Property 2: Preservation - Non-stale sends, history, isolation, reset, and
 * authorization unchanged.
 *
 * Generator-style properties use seeded fake() loops, because no PBT library is installed.
 * Every case keeps isBugCondition false: one sidebar per (review, user), no reset in another
 * tab, and no stale page.
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12**
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

const PRESERVATION_ITERATIONS = 25;

const PRESERVATION_WORKSPACE_KEYS = ['workflow', 'documents', 'legislation', 'stakeholders', 'consultations', 'analysis', 'reports'];

const PRESERVATION_UNAVAILABLE_ERROR = 'The assistant is unavailable right now. Check the AI provider configuration or try again.';

/**
 * Prompts the refusal guard refuses on a fresh review, with the synthetic reply it stores.
 *
 * @return array<string, array{prompt: string, reply: string}>
 */
function preservationRefusals(): array
{
    return [
        'workflow' => [
            'prompt' => 'Draft findings from this workflow stage.',
            'reply' => 'I can help with workflow stages and next steps in this tab, but I cannot generate findings, recommendations, or conclusions from the Workflow tab.',
        ],
        'documents' => [
            'prompt' => 'Give me a policy impact reading of these documents.',
            'reply' => 'I can summarize and compare evidence in this tab, but I cannot provide impact analysis from the Evidence tab.',
        ],
        'legislation' => [
            'prompt' => 'Evaluate the impact of this law.',
            'reply' => 'I can explain the structure of the legislation in this tab, but I cannot evaluate impact or give final conclusions without supporting evidence.',
        ],
        'stakeholders' => [
            'prompt' => 'Who would oppose this reform?',
            'reply' => 'I can help map stakeholders and identify gaps here, but I cannot assume stakeholder views or make political inferences without grounded source material.',
        ],
        'consultations' => [
            'prompt' => 'Analyze the consultation results.',
            'reply' => 'I can help design consultation activity in this tab, but I cannot analyze consultation results because no consultations or submissions are recorded yet.',
        ],
        'analysis' => [
            'prompt' => 'Present the final conclusions for this review.',
            'reply' => 'I can help with provisional findings, themes, and recommendation options in this tab, but I cannot present them as final conclusions.',
        ],
        'reports' => [
            'prompt' => 'Write the final report.',
            'reply' => 'I can help with report structure and provisional drafting here, but I cannot treat this as final publication language until the report record shows a published outcome.',
        ],
    ];
}

/**
 * The reply the closure fake gives to an answered prompt.
 */
function preservationReplyFor(string $prompt): string
{
    return 'Reply to: '.$prompt;
}

function preservationFakeAgentReplies(): void
{
    ReviewAssistantAgent::fake(fn (string $prompt): string => preservationReplyFor($prompt));
}

function preservationMountSidebar(PlsReview $review, string $workspaceKey = 'workflow'): mixed
{
    return Livewire::test(AssistantSidebar::class, [
        'review' => $review->fresh(),
        'workspaceKey' => $workspaceKey,
    ]);
}

/**
 * Plan one send: an answered prompt, or the workspace's refused prompt.
 *
 * @return array{prompt: string, reply: string, refused: bool}
 */
function preservationPlanSend(string $workspaceKey, bool $refused, string $label): array
{
    if ($refused) {
        return [...preservationRefusals()[$workspaceKey], 'refused' => true];
    }

    $prompt = 'Summarize the current step ('.$label.').';

    return ['prompt' => $prompt, 'reply' => preservationReplyFor($prompt), 'refused' => false];
}

/**
 * @param  list<array{prompt: string, reply: string}>  $sends
 * @return list<array{content: string, role: string}>
 */
function preservationExpectedMessages(array $sends): array
{
    return collect($sends)
        ->flatMap(fn (array $send): array => [
            ['content' => $send['prompt'], 'role' => 'user'],
            ['content' => $send['reply'], 'role' => 'assistant'],
        ])
        ->values()
        ->all();
}

/**
 * @return list<object>
 */
function preservationConversationRows(PlsReview $review, int $userId): array
{
    return DB::table('agent_conversations')
        ->where('pls_review_id', $review->id)
        ->where('user_id', $userId)
        ->get()
        ->all();
}

/**
 * Stored messages for a conversation, oldest first, as role and content.
 *
 * @return list<array{content: string, role: string}>
 */
function preservationStoredMessages(?string $conversationId): array
{
    if ($conversationId === null) {
        return [];
    }

    return DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->orderBy('id')
        ->get()
        ->map(fn (object $row): array => ['content' => $row->content, 'role' => $row->role])
        ->all();
}

/**
 * Full rows for a (review, user) conversation and its messages, to prove they are untouched.
 *
 * @return array{conversations: list<array<string, mixed>>, messages: list<array<string, mixed>>}
 */
function preservationSnapshot(PlsReview $review, int $userId): array
{
    $conversations = collect(preservationConversationRows($review, $userId))
        ->map(fn (object $row): array => (array) $row)
        ->all();

    return [
        'conversations' => $conversations,
        'messages' => DB::table('agent_conversation_messages')
            ->whereIn('conversation_id', array_column($conversations, 'id'))
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all(),
    ];
}

function preservationGrantViewAccess(PlsReview $review, User $user, User $invitedBy): void
{
    $review->memberships()->create([
        'user_id' => $user->id,
        'role' => PlsReviewMembershipRole::Contributor,
        'invited_by' => $invitedBy->id,
    ]);
}

/**
 * Invariants that hold after every non-bug-condition sequence on unfixed code.
 */
function preservationAssertNoOrphans(string $context): void
{
    expect(DB::table('agent_conversations')->whereNull('pls_review_id')->count())
        ->toBe(0, "unlinked conversations ({$context})")
        ->and(
            DB::table('agent_conversation_messages')
                ->whereNotIn('conversation_id', DB::table('agent_conversations')->select('id'))
                ->count()
        )->toBe(0, "messages without a conversation ({$context})");
}

/**
 * Seed a linked review conversation with alternating messages, oldest first.
 */
function preservationSeedConversation(PlsReview $review, int $userId, int $messageCount): string
{
    $conversationId = (string) Str::uuid7(now()->subMinutes(10));

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $userId,
        'pls_review_id' => $review->id,
        'title' => 'Seeded conversation',
        'playbook_version' => app(PlaybookRepository::class)->versionForTab('workflow'),
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    foreach (range(1, $messageCount) as $index) {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(now()->subMinutes(5)->addMilliseconds($index)),
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'agent' => ReviewAssistantAgent::class,
            'role' => $index % 2 === 1 ? 'user' : 'assistant',
            'content' => 'Seeded message '.$index,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
    }

    return $conversationId;
}

test('single sidebar sequences keep one conversation with every exchange in send order', function () {
    preservationFakeAgentReplies();

    foreach (range(1, PRESERVATION_ITERATIONS) as $iteration) {
        $seed = 3100 + $iteration;
        fake()->seed($seed);

        $workspaceKey = fake()->randomElement(PRESERVATION_WORKSPACE_KEYS);
        $sends = collect(range(1, fake()->numberBetween(1, 5)))
            ->map(fn (int $sendNumber): array => preservationPlanSend(
                $workspaceKey,
                fake()->boolean(35),
                "seed {$seed} send {$sendNumber}",
            ))
            ->all();

        $review = plsReview(['title' => "Preservation sequence {$seed}"]);
        $sidebar = preservationMountSidebar($review, $workspaceKey);

        foreach ($sends as $send) {
            $sidebar->call('sendPrompt', $send['prompt']);
        }

        $expected = preservationExpectedMessages($sends);
        $rows = preservationConversationRows($review, auth()->id());

        expect($rows)->toHaveCount(1, "seed {$seed}")
            ->and(preservationStoredMessages($rows[0]->id))->toBe($expected, "seed {$seed}")
            ->and($sidebar->get('assistantMessages'))->toEqual($expected, "seed {$seed}")
            ->and($sidebar->get('assistantError'))->toBeNull()
            ->and($rows[0]->playbook_version)->toBe(app(PlaybookRepository::class)->versionForTab($workspaceKey), "seed {$seed}")
            ->and(preservationMountSidebar($review, $workspaceKey)->get('assistantMessages'))->toEqual($expected, "seed {$seed}");

        preservationAssertNoOrphans("seed {$seed}");
    }
});

test('stored message rows keep the observed column values', function (string $prompt, string $reply) {
    preservationFakeAgentReplies();

    $review = plsReview(['title' => 'Stored column shape review']);

    preservationMountSidebar($review)->call('sendPrompt', $prompt);

    $rows = preservationConversationRows($review, auth()->id());

    expect($rows)->toHaveCount(1);

    $messages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $rows[0]->id)
        ->orderBy('id')
        ->get()
        ->map(fn (object $row): array => collect((array) $row)->except(['id', 'created_at', 'updated_at'])->all())
        ->all();

    $assistantShape = [
        'usage' => [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cache_write_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'reasoning_tokens' => 0,
        ],
        'meta' => ['provider' => 'openai', 'model' => 'gpt-5.4', 'citations' => []],
    ];

    expect($messages)->toHaveCount(2)
        ->and(collect($messages[0])->except(['usage', 'meta'])->all())->toEqual([
            'conversation_id' => $rows[0]->id,
            'user_id' => auth()->id(),
            'agent' => ReviewAssistantAgent::class,
            'role' => 'user',
            'content' => $prompt,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
        ])
        ->and(json_decode($messages[0]['usage'], true))->toBe([])
        ->and(json_decode($messages[0]['meta'], true))->toBe([])
        ->and(collect($messages[1])->except(['usage', 'meta'])->all())->toEqual([
            'conversation_id' => $rows[0]->id,
            'user_id' => auth()->id(),
            'agent' => ReviewAssistantAgent::class,
            'role' => 'assistant',
            'content' => $reply,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
        ])
        ->and(json_decode($messages[1]['usage'], true))->toBe($assistantShape['usage'])
        ->and(json_decode($messages[1]['meta'], true))->toBe($assistantShape['meta']);
})->with([
    'ai path' => ['Summarize the current step.', 'Reply to: Summarize the current step.'],
    'refusal path' => [
        'Draft findings from this workflow stage.',
        'I can help with workflow stages and next steps in this tab, but I cannot generate findings, recommendations, or conclusions from the Workflow tab.',
    ],
]);

test('the agent receives the latest 20 conversation messages oldest first as history', function (int $seededMessageCount) {
    $review = plsReview(['title' => 'History window review']);
    $conversationId = preservationSeedConversation($review, auth()->id(), $seededMessageCount);
    $prompt = 'Summarize the current step.';

    $toRoleAndContent = fn (Message $message): array => [
        'content' => (string) $message->content,
        'role' => $message->role->value,
    ];

    $expectedHistory = resolve(ConversationStore::class)
        ->getLatestConversationMessages($conversationId, 20)
        ->map($toRoleAndContent)
        ->all();

    $historyAtProviderCall = null;

    ReviewAssistantAgent::fake(function (string $receivedPrompt) use ($prompt, $toRoleAndContent, &$historyAtProviderCall): string {
        ReviewAssistantAgent::assertPrompted(function (AgentPrompt $agentPrompt) use ($prompt, $toRoleAndContent, &$historyAtProviderCall): bool {
            if ($agentPrompt->prompt !== $prompt) {
                return false;
            }

            $historyAtProviderCall = collect($agentPrompt->agent->messages())->map($toRoleAndContent)->all();

            return true;
        });

        return preservationReplyFor($receivedPrompt);
    });

    $sidebar = preservationMountSidebar($review)->call('sendPrompt', $prompt);

    $seededContents = collect(range(1, $seededMessageCount))
        ->map(fn (int $index): array => [
            'content' => 'Seeded message '.$index,
            'role' => $index % 2 === 1 ? 'user' : 'assistant',
        ]);

    $expectedDisplay = $seededContents
        ->concat([
            ['content' => $prompt, 'role' => 'user'],
            ['content' => preservationReplyFor($prompt), 'role' => 'assistant'],
        ])
        ->slice(-20)
        ->values()
        ->all();

    expect($sidebar->get('assistantError'))->toBeNull()
        ->and($expectedHistory)->toBe($seededContents->slice(-20)->values()->all())
        ->and($historyAtProviderCall)->toBe($expectedHistory)
        ->and(preservationConversationRows($review, auth()->id()))->toHaveCount(1)
        ->and(preservationStoredMessages($conversationId))->toHaveCount($seededMessageCount + 2)
        ->and($sidebar->get('assistantMessages'))->toEqual($expectedDisplay);
})->with([
    'one message' => [1],
    'nineteen messages' => [19],
    'twenty messages' => [20],
    'twenty five messages' => [25],
]);

test('tab changes reuse the conversation and record the last tab playbook version', function () {
    preservationFakeAgentReplies();
    $playbooks = app(PlaybookRepository::class);

    foreach (range(1, PRESERVATION_ITERATIONS) as $iteration) {
        $seed = 4200 + $iteration;
        fake()->seed($seed);

        $tabs = collect(range(1, fake()->numberBetween(2, 4)))
            ->map(fn (): string => fake()->randomElement(PRESERVATION_WORKSPACE_KEYS))
            ->all();
        $refusedFlags = collect($tabs)->map(fn (): bool => fake()->boolean(30))->all();

        $review = plsReview(['title' => "Preservation tabs {$seed}"]);
        $sends = [];
        $conversationId = null;

        foreach ($tabs as $index => $workspaceKey) {
            $sidebar = preservationMountSidebar($review, $workspaceKey);

            expect($sidebar->get('assistantConversationId'))->toBe($conversationId, "seed {$seed} tab {$index}")
                ->and($sidebar->get('assistantMessages'))->toEqual(preservationExpectedMessages($sends), "seed {$seed} tab {$index}");

            $send = preservationPlanSend($workspaceKey, $refusedFlags[$index], "seed {$seed} tab {$index}");
            $sidebar->call('sendPrompt', $send['prompt']);
            $sends[] = $send;

            $rows = preservationConversationRows($review, auth()->id());
            $conversationId ??= $rows[0]->id ?? null;

            expect($rows)->toHaveCount(1, "seed {$seed} tab {$index}")
                ->and($rows[0]->id)->toBe($conversationId, "seed {$seed} tab {$index}")
                ->and($rows[0]->playbook_version)->toBe($playbooks->versionForTab($workspaceKey), "seed {$seed} tab {$index}")
                ->and($sidebar->get('assistantConversationId'))->toBe($conversationId, "seed {$seed} tab {$index}")
                ->and($sidebar->get('assistantMessages'))->toEqual(preservationExpectedMessages($sends), "seed {$seed} tab {$index}");
        }

        expect(preservationStoredMessages($conversationId))->toBe(preservationExpectedMessages($sends), "seed {$seed}");

        preservationAssertNoOrphans("seed {$seed}");
    }
});

test('conversations stay separate per review and per user with no cross display', function () {
    preservationFakeAgentReplies();

    foreach (range(1, PRESERVATION_ITERATIONS) as $iteration) {
        $seed = 5300 + $iteration;
        fake()->seed($seed);

        $reviewCount = fake()->numberBetween(1, 3);
        $userCount = fake()->numberBetween(1, 2);
        $workspaceKey = fake()->randomElement(PRESERVATION_WORKSPACE_KEYS);
        $sendCounts = collect(range(1, $reviewCount * $userCount))
            ->map(fn (): int => fake()->numberBetween(0, 2))
            ->all();

        if (array_sum($sendCounts) === 0) {
            $sendCounts[0] = 1;
        }

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $reviews = collect(range(1, $reviewCount))
            ->map(fn (int $number): PlsReview => plsReview(['title' => "Preservation isolation {$seed} review {$number}"]))
            ->all();
        $users = [$owner];

        if ($userCount === 2) {
            $users[] = User::factory()->create();

            foreach ($reviews as $review) {
                preservationGrantViewAccess($review, $users[1], $owner);
            }
        }

        $pairs = [];

        foreach ($reviews as $reviewIndex => $review) {
            foreach ($users as $userIndex => $user) {
                $this->actingAs($user);

                $pairs[] = [
                    'label' => "review {$reviewIndex} user {$userIndex}",
                    'review' => $review,
                    'user' => $user,
                    'sidebar' => preservationMountSidebar($review, $workspaceKey),
                    'sends' => [],
                ];
            }
        }

        $sendOrder = collect($sendCounts)
            ->flatMap(fn (int $count, int $pairIndex): array => array_fill(0, $count, $pairIndex))
            ->all();
        $sendOrder = fake()->shuffleArray($sendOrder);

        foreach ($sendOrder as $step => $pairIndex) {
            $pair = &$pairs[$pairIndex];
            $send = preservationPlanSend($workspaceKey, false, "seed {$seed} {$pair['label']} step {$step}");

            $this->actingAs($pair['user']);
            $pair['sidebar']->call('sendPrompt', $send['prompt']);
            $pair['sends'][] = $send;
            unset($pair);
        }

        foreach ($pairs as $pair) {
            $context = "seed {$seed} {$pair['label']}";
            $expected = preservationExpectedMessages($pair['sends']);
            $rows = preservationConversationRows($pair['review'], $pair['user']->id);

            $this->actingAs($pair['user']);

            expect($rows)->toHaveCount($pair['sends'] === [] ? 0 : 1, $context)
                ->and(preservationStoredMessages($rows[0]->id ?? null))->toBe($expected, $context)
                ->and($pair['sidebar']->get('assistantMessages'))->toEqual($expected, $context)
                ->and(preservationMountSidebar($pair['review'], $workspaceKey)->get('assistantMessages'))->toEqual($expected, $context);
        }

        $sendingPairs = collect($pairs)->filter(fn (array $pair): bool => $pair['sends'] !== [])->count();

        expect(
            DB::table('agent_conversations')
                ->whereIn('pls_review_id', collect($reviews)->pluck('id'))
                ->count()
        )->toBe($sendingPairs, "seed {$seed}");

        preservationAssertNoOrphans("seed {$seed}");
    }
});

test('reset deletes only the current review and user conversation and clears the resetting tab', function (int $priorSends) {
    preservationFakeAgentReplies();

    $userA = auth()->user();
    $userB = User::factory()->create();
    $reviewOne = plsReview(['title' => 'Reset review one']);
    $reviewTwo = plsReview(['title' => 'Reset review two']);
    preservationGrantViewAccess($reviewOne, $userB, $userA);

    $sidebarA = preservationMountSidebar($reviewOne);

    foreach (range(1, $priorSends) as $sendNumber) {
        $sidebarA->call('sendPrompt', "Summarize the current step (prior {$sendNumber}).");
    }

    preservationMountSidebar($reviewTwo)->call('sendPrompt', 'Summarize the current step (review two).');

    $this->actingAs($userB);
    preservationMountSidebar($reviewOne)->call('sendPrompt', 'Summarize the current step (user b).');
    $this->actingAs($userA);

    $resetConversationId = preservationConversationRows($reviewOne, $userA->id)[0]->id;
    $otherReviewSnapshot = preservationSnapshot($reviewTwo, $userA->id);
    $otherUserSnapshot = preservationSnapshot($reviewOne, $userB->id);

    expect(preservationStoredMessages($resetConversationId))->toHaveCount($priorSends * 2);

    $sidebarA
        ->set('assistantInput', 'Unsent draft')
        ->set('assistantError', 'Earlier error')
        ->call('resetAssistantConversation')
        ->assertSet('assistantConversationId', null)
        ->assertSet('assistantMessages', [])
        ->assertSet('assistantInput', '')
        ->assertSet('assistantError', null)
        ->assertSee('How this assistant can help here');

    expect(preservationConversationRows($reviewOne, $userA->id))->toBe([])
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $resetConversationId)->count())->toBe(0)
        ->and(preservationSnapshot($reviewTwo, $userA->id))->toEqual($otherReviewSnapshot)
        ->and(preservationSnapshot($reviewOne, $userB->id))->toEqual($otherUserSnapshot);

    $sidebarA->call('sendPrompt', 'Summarize the current step (after reset).');

    $rows = preservationConversationRows($reviewOne, $userA->id);
    $expected = preservationExpectedMessages([
        preservationPlanSend('workflow', false, 'after reset'),
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->id)->not->toBe($resetConversationId)
        ->and(preservationStoredMessages($rows[0]->id))->toBe($expected)
        ->and($sidebarA->get('assistantMessages'))->toEqual($expected)
        ->and(preservationSnapshot($reviewTwo, $userA->id))->toEqual($otherReviewSnapshot)
        ->and(preservationSnapshot($reviewOne, $userB->id))->toEqual($otherUserSnapshot);

    preservationAssertNoOrphans('after reset');
})->with([
    'one prior send' => [1],
    'three prior sends' => [3],
]);

test('the refusal guard answers without prompting the agent and stores the exchange', function (string $workspaceKey) {
    ReviewAssistantAgent::fake();

    $refusal = preservationRefusals()[$workspaceKey];
    $review = plsReview(['title' => "Refusal review {$workspaceKey}"]);

    $sidebar = preservationMountSidebar($review, $workspaceKey)
        ->call('sendPrompt', $refusal['prompt'])
        ->assertSee($refusal['reply'])
        ->call('sendPrompt', $refusal['prompt']);

    ReviewAssistantAgent::assertNeverPrompted();

    $rows = preservationConversationRows($review, auth()->id());
    $expected = preservationExpectedMessages([$refusal, $refusal]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->playbook_version)->toBe(app(PlaybookRepository::class)->versionForTab($workspaceKey))
        ->and(preservationStoredMessages($rows[0]->id))->toBe($expected)
        ->and($sidebar->get('assistantMessages'))->toEqual($expected);

    preservationAssertNoOrphans("refusal {$workspaceKey}");
})->with(array_keys(preservationRefusals()));

test('a provider failure reports the exception, shows the inline error, keeps the local user message, and stores no messages', function (int $priorSends) {
    Exceptions::fake();

    $providerFails = false;

    ReviewAssistantAgent::fake(function (string $prompt) use (&$providerFails): string {
        if ($providerFails) {
            throw new RuntimeException('AI provider unavailable');
        }

        return preservationReplyFor($prompt);
    });

    $review = plsReview(['title' => 'Provider failure review']);
    $sidebar = preservationMountSidebar($review);
    $priorExchanges = [];

    foreach (range(1, $priorSends) as $sendNumber) {
        $send = preservationPlanSend('workflow', false, "prior {$sendNumber}");
        $sidebar->call('sendPrompt', $send['prompt']);
        $priorExchanges[] = $send;
    }

    $messagesBefore = DB::table('agent_conversation_messages')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();

    $providerFails = true;

    $sidebar
        ->call('sendPrompt', 'Summarize the current step (failing).')
        ->assertSet('assistantError', PRESERVATION_UNAVAILABLE_ERROR)
        ->assertSee(PRESERVATION_UNAVAILABLE_ERROR);

    Exceptions::assertReported(RuntimeException::class);

    expect($sidebar->get('assistantMessages'))->toEqual([
        ...preservationExpectedMessages($priorExchanges),
        ['role' => 'user', 'content' => 'Summarize the current step (failing).'],
    ])
        ->and(DB::table('agent_conversation_messages')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all())->toEqual($messagesBefore)
        ->and(DB::table('agent_conversation_messages')->where('content', 'Summarize the current step (failing).')->exists())->toBeFalse();

    preservationAssertNoOrphans('provider failure');
})->with([
    'no conversation yet' => [0],
    'existing conversation' => [2],
]);

test('provisional findings reach the analysis workspace', function () {
    $generatedFinding = <<<'TEXT'
POTENTIAL FINDING:
Title: Reporting remains incomplete
Type: implementation gap
Draft: The available evidence suggests reporting is incomplete.
Supporting record: Annual report: "Reporting was delayed."
Limitations: Agency responses have not been recorded.
Reviewer question: Has the missing report now been published?
END FINDING
TEXT;

    ReviewAssistantAgent::fake([$generatedFinding]);

    $review = plsReview(['title' => 'Provisional findings hand-off review']);
    $deliveredContent = null;

    preservationMountSidebar($review, 'analysis')
        ->call('sendRequestedPrompt', 'Suggest provisional findings from the saved review record.', provisionalFindings: true)
        ->assertSet('assistantError', null)
        ->assertDispatchedTo(AnalysisPage::class, 'provisional-findings-generated', function (string $name, array $params) use (&$deliveredContent): bool {
            $deliveredContent = $params['content'] ?? null;

            return true;
        })
        ->assertNotDispatched('provisional-findings-failed');

    expect($deliveredContent)->toBe($generatedFinding)
        ->and(preservationStoredMessages(preservationConversationRows($review, auth()->id())[0]->id))->toBe([
            ['content' => 'Suggest provisional findings from the saved review record.', 'role' => 'user'],
            ['content' => $generatedFinding, 'role' => 'assistant'],
        ]);

    Livewire::test(AnalysisPage::class, ['review' => $review])
        ->dispatch('provisional-findings-generated', content: $deliveredContent)
        ->assertSet('awaitingProvisionalFindings', false)
        ->assertSet('provisionalFindingsError', null)
        ->assertSet('provisionalFindings.0.title', 'Reporting remains incomplete');
});

test('report outlines reach the reports workspace', function () {
    $generatedOutline = <<<'TEXT'
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

    ReviewAssistantAgent::fake([$generatedOutline]);

    $review = plsReview(['title' => 'Report outline hand-off review']);
    $prompt = 'Prepare a provisional PLS report outline from the saved review record.';
    $deliveredContent = null;

    preservationMountSidebar($review, 'reports')
        ->call('sendRequestedPrompt', $prompt, reportOutline: true)
        ->assertSet('assistantError', null)
        ->assertDispatchedTo(ReportsPage::class, 'report-outline-generated', function (string $name, array $params) use (&$deliveredContent): bool {
            $deliveredContent = $params['content'] ?? null;

            return true;
        })
        ->assertNotDispatched('report-outline-failed');

    expect($deliveredContent)->toBe($generatedOutline)
        ->and(preservationStoredMessages(preservationConversationRows($review, auth()->id())[0]->id))->toBe([
            ['content' => $prompt, 'role' => 'user'],
            ['content' => $generatedOutline, 'role' => 'assistant'],
        ]);

    Livewire::test(ReportsPage::class, ['review' => $review])
        ->dispatch('report-outline-generated', content: $deliveredContent)
        ->assertSet('awaitingReportOutline', false)
        ->assertSet('reportOutlineError', null)
        ->assertSet('reportOutline.title', 'Draft report on implementation')
        ->assertSet('reportOutline.sections.0.title', 'Purpose and scope');
});

test('a user without view access cannot mount the sidebar and no conversation is created', function (string $scenario) {
    ReviewAssistantAgent::fake();

    $owner = User::factory()->create();
    $review = plsReview(['title' => 'Unauthorized sidebar review', 'created_by' => $owner->id]);

    $deniedUser = match ($scenario) {
        'non-member' => User::factory()->create(),
        'creator without membership' => $owner,
    };

    if ($scenario === 'creator without membership') {
        DB::table('pls_review_memberships')->where('pls_review_id', $review->id)->delete();
    }

    $this->actingAs($deniedUser);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])->assertForbidden();

    ReviewAssistantAgent::assertNeverPrompted();

    expect(DB::table('agent_conversations')->where('pls_review_id', $review->id)->count())->toBe(0)
        ->and(DB::table('agent_conversations')->where('user_id', $deniedUser->id)->count())->toBe(0)
        ->and(DB::table('agent_conversation_messages')->count())->toBe(0);
})->with([
    'non-member',
    'creator without membership',
]);
