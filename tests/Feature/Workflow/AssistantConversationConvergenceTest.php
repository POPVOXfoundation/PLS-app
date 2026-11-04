<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Bug condition exploration for Nightwatch #7.
 *
 * Property 1: Bug Condition - Sends resolve server-side, converge on one review
 * conversation, and never write after an in-flight reset.
 *
 * **Validates: Requirements 1.1, 1.3, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10, 2.1, 2.3, 2.5, 2.6, 2.8, 2.10, 2.11, 2.13**
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

const CONVERGENCE_RESET_NOTICE = 'This chat was reset in another window. Your message was not saved.';

const CONVERGENCE_WORKFLOW_REFUSAL = 'I can help with workflow stages and next steps in this tab, but I cannot generate findings, recommendations, or conclusions from the Workflow tab.';

/**
 * Record every insert into the assistant conversation tables, in execution order.
 *
 * @return ArrayObject<int, array{table: string, sql: string}>
 */
function convergenceInsertRecorder(): ArrayObject
{
    $recorder = new ArrayObject;

    DB::listen(function (QueryExecuted $query) use ($recorder): void {
        if (preg_match('/^\s*insert\s+into\s+["`]?(agent_conversations|agent_conversation_messages)["`]?\s/i', $query->sql, $matches) === 1) {
            $recorder->append([
                'table' => strtolower($matches[1]),
                'sql' => $query->sql,
            ]);
        }
    });

    return $recorder;
}

/**
 * @param  ArrayObject<int, array{table: string, sql: string}>  $recorder
 */
function convergenceInsertCount(ArrayObject $recorder, string $table, int $fromIndex = 0, ?int $toIndex = null): int
{
    return collect($recorder->getArrayCopy())
        ->slice($fromIndex, $toIndex === null ? null : $toIndex - $fromIndex)
        ->where('table', $table)
        ->count();
}

function convergenceMountSidebar(PlsReview $review, string $workspaceKey = 'workflow'): mixed
{
    return Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => $workspaceKey,
    ]);
}

/**
 * @return list<array{role: string, content: string}>
 */
function convergenceMessagesFor(?string $conversationId): array
{
    if ($conversationId === null) {
        return [];
    }

    return DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->orderBy('id')
        ->get(['role', 'content'])
        ->map(fn (object $message): array => [
            'role' => $message->role,
            'content' => $message->content,
        ])
        ->all();
}

/**
 * Observable database state for one (review, user) pair.
 *
 * @return array{
 *     review_conversation_rows: int,
 *     unlinked_conversation_rows: int,
 *     orphan_message_rows: int,
 *     review_conversation_messages: list<array{role: string, content: string}>
 * }
 */
function convergenceSnapshot(PlsReview $review, int $userId): array
{
    $reviewConversationIds = DB::table('agent_conversations')
        ->where('pls_review_id', $review->getKey())
        ->where('user_id', $userId)
        ->pluck('id');

    return [
        'review_conversation_rows' => $reviewConversationIds->count(),
        'unlinked_conversation_rows' => DB::table('agent_conversations')->whereNull('pls_review_id')->count(),
        'orphan_message_rows' => DB::table('agent_conversation_messages')
            ->whereNotIn('conversation_id', DB::table('agent_conversations')->select('id'))
            ->count(),
        'review_conversation_messages' => convergenceMessagesFor($reviewConversationIds->first()),
    ];
}

function convergenceReviewConversationId(PlsReview $review, int $userId): ?string
{
    return DB::table('agent_conversations')
        ->where('pls_review_id', $review->getKey())
        ->where('user_id', $userId)
        ->value('id');
}

test('case A: a stale page on the AI path stores its exchange in the existing review conversation', function () {
    $review = plsReview(['title' => 'Convergence case A']);

    ReviewAssistantAgent::fake(fn (string $prompt): string => match ($prompt) {
        'First prompt' => 'First reply',
        'Second prompt' => 'Second reply',
        default => 'Title',
    });

    $pageA = convergenceMountSidebar($review);
    $pageB = convergenceMountSidebar($review);

    $pageA->call('sendPrompt', 'First prompt');
    $pageB->call('sendPrompt', 'Second prompt');

    $expectedMessages = [
        ['role' => 'user', 'content' => 'First prompt'],
        ['role' => 'assistant', 'content' => 'First reply'],
        ['role' => 'user', 'content' => 'Second prompt'],
        ['role' => 'assistant', 'content' => 'Second reply'],
    ];

    expect(convergenceSnapshot($review, auth()->id()))->toBe([
        'review_conversation_rows' => 1,
        'unlinked_conversation_rows' => 0,
        'orphan_message_rows' => 0,
        'review_conversation_messages' => $expectedMessages,
    ])
        ->and($pageB->get('assistantMessages'))->toEqual($expectedMessages);
});

test('case B: a stale analysis page on the incident path stores provisional findings in the existing review conversation', function () {
    $review = plsReview(['title' => 'Convergence case B']);

    ReviewAssistantAgent::fake(fn (string $prompt): string => match ($prompt) {
        'First prompt' => 'First reply',
        'Suggest provisional findings' => 'Provisional findings reply',
        default => 'Title',
    });

    $pageA = convergenceMountSidebar($review);
    $pageB = convergenceMountSidebar($review, 'analysis');

    $pageA->call('sendPrompt', 'First prompt');
    $pageB->call('sendRequestedPrompt', 'Suggest provisional findings', true);

    $expectedMessages = [
        ['role' => 'user', 'content' => 'First prompt'],
        ['role' => 'assistant', 'content' => 'First reply'],
        ['role' => 'user', 'content' => 'Suggest provisional findings'],
        ['role' => 'assistant', 'content' => 'Provisional findings reply'],
    ];

    expect(convergenceSnapshot($review, auth()->id()))->toBe([
        'review_conversation_rows' => 1,
        'unlinked_conversation_rows' => 0,
        'orphan_message_rows' => 0,
        'review_conversation_messages' => $expectedMessages,
    ])
        ->and($pageB->get('assistantMessages'))->toEqual($expectedMessages);
});

test('case C: a stale page on the refusal path stores the synthetic exchange in the existing review conversation', function () {
    $review = plsReview(['title' => 'Convergence case C']);

    ReviewAssistantAgent::fake(fn (string $prompt): string => match ($prompt) {
        'First prompt' => 'First reply',
        default => 'Title',
    });

    $pageA = convergenceMountSidebar($review);
    $pageB = convergenceMountSidebar($review);

    $pageA->call('sendPrompt', 'First prompt');
    $pageB->call('sendPrompt', 'Draft findings from this workflow stage.');

    $expectedMessages = [
        ['role' => 'user', 'content' => 'First prompt'],
        ['role' => 'assistant', 'content' => 'First reply'],
        ['role' => 'user', 'content' => 'Draft findings from this workflow stage.'],
        ['role' => 'assistant', 'content' => CONVERGENCE_WORKFLOW_REFUSAL],
    ];

    expect(convergenceSnapshot($review, auth()->id()))->toBe([
        'review_conversation_rows' => 1,
        'unlinked_conversation_rows' => 0,
        'orphan_message_rows' => 0,
        'review_conversation_messages' => $expectedMessages,
    ])
        ->and($pageB->get('assistantMessages'))->toEqual($expectedMessages);
});

test('case D: a send from a tab holding a conversation reset in another tab creates a fresh review conversation', function () {
    $review = plsReview(['title' => 'Convergence case D']);

    ReviewAssistantAgent::fake(fn (string $prompt): string => match ($prompt) {
        'Seed prompt' => 'Seed reply',
        'B prompt' => 'B reply',
        default => 'Title',
    });

    convergenceMountSidebar($review)->call('sendPrompt', 'Seed prompt');
    $deletedConversationId = convergenceReviewConversationId($review, auth()->id());

    $pageA = convergenceMountSidebar($review)->assertSet('assistantConversationId', $deletedConversationId);
    $pageB = convergenceMountSidebar($review)->assertSet('assistantConversationId', $deletedConversationId);

    $pageA->call('resetAssistantConversation');
    $pageB->call('sendPrompt', 'B prompt');

    $expectedMessages = [
        ['role' => 'user', 'content' => 'B prompt'],
        ['role' => 'assistant', 'content' => 'B reply'],
    ];

    expect([
        ...convergenceSnapshot($review, auth()->id()),
        'messages_on_deleted_conversation' => DB::table('agent_conversation_messages')
            ->where('conversation_id', $deletedConversationId)
            ->count(),
    ])->toBe([
        'review_conversation_rows' => 1,
        'unlinked_conversation_rows' => 0,
        'orphan_message_rows' => 0,
        'review_conversation_messages' => $expectedMessages,
        'messages_on_deleted_conversation' => 0,
    ])
        ->and($pageB->get('assistantMessages'))->toEqual($expectedMessages);
});

test('case E: a reset in another tab while the AI reply is in flight discards the exchange', function () {
    Exceptions::fake();

    $review = plsReview(['title' => 'Convergence case E']);
    $recorder = convergenceInsertRecorder();
    $pageA = null;
    $hasResetDuringProviderCall = false;
    $insertCountAfterReset = null;

    ReviewAssistantAgent::fake(function (string $prompt) use (&$pageA, &$hasResetDuringProviderCall, &$insertCountAfterReset, $recorder): string {
        if ($prompt === 'B prompt' && ! $hasResetDuringProviderCall) {
            $hasResetDuringProviderCall = true;

            // Variant: nested Livewire test call on tab A inside tab B's provider call.
            $pageA->call('resetAssistantConversation');

            $insertCountAfterReset = $recorder->count();

            return 'B reply';
        }

        return match ($prompt) {
            'Seed prompt' => 'Seed reply',
            default => 'Title',
        };
    });

    $pageA = convergenceMountSidebar($review);
    $pageA->call('sendPrompt', 'Seed prompt');
    $deletedConversationId = convergenceReviewConversationId($review, auth()->id());

    $pageB = convergenceMountSidebar($review)->assertSet('assistantConversationId', $deletedConversationId);

    $pageB->call('sendPrompt', 'B prompt');

    expect($hasResetDuringProviderCall)->toBeTrue();

    expect([
        'message_inserts_after_reset' => convergenceInsertCount($recorder, 'agent_conversation_messages', $insertCountAfterReset),
        'review_conversation_rows' => DB::table('agent_conversations')
            ->where('pls_review_id', $review->getKey())
            ->where('user_id', auth()->id())
            ->count(),
        'messages_on_deleted_conversation' => DB::table('agent_conversation_messages')
            ->where('conversation_id', $deletedConversationId)
            ->count(),
        'page_b_error' => $pageB->get('assistantError'),
        'page_b_messages' => $pageB->get('assistantMessages'),
        'page_b_conversation_id' => $pageB->get('assistantConversationId'),
    ])->toBe([
        'message_inserts_after_reset' => 0,
        'review_conversation_rows' => 0,
        'messages_on_deleted_conversation' => 0,
        'page_b_error' => CONVERGENCE_RESET_NOTICE,
        'page_b_messages' => [],
        'page_b_conversation_id' => null,
    ]);

    Exceptions::assertNothingReported();
});

test('case H: a reset and a new conversation in another tab while the AI reply is in flight leave the new conversation untouched', function () {
    Exceptions::fake();

    $review = plsReview(['title' => 'Convergence case H']);
    $pageA = null;
    $hasRunTabAActions = false;
    $newConversationId = null;
    $newConversationSnapshot = null;

    ReviewAssistantAgent::fake(function (string $prompt) use (&$pageA, &$hasRunTabAActions, &$newConversationId, &$newConversationSnapshot, $review): string {
        if ($prompt === 'B prompt' && ! $hasRunTabAActions) {
            $hasRunTabAActions = true;

            // Variant: nested Livewire test calls on tab A inside tab B's provider call.
            $pageA->call('resetAssistantConversation');
            $pageA->call('sendPrompt', 'A prompt');

            $newConversationId = convergenceReviewConversationId($review, auth()->id());
            $newConversationSnapshot = convergenceMessagesFor($newConversationId);

            return 'B reply';
        }

        return match ($prompt) {
            'Seed prompt' => 'Seed reply',
            default => 'A reply',
        };
    });

    $pageA = convergenceMountSidebar($review);
    $pageA->call('sendPrompt', 'Seed prompt');
    $deletedConversationId = convergenceReviewConversationId($review, auth()->id());

    $pageB = convergenceMountSidebar($review)->assertSet('assistantConversationId', $deletedConversationId);

    $pageB->call('sendPrompt', 'B prompt');

    expect($hasRunTabAActions)->toBeTrue()
        ->and($newConversationId)->not->toBeNull()
        ->and($newConversationId)->not->toBe($deletedConversationId);

    $tabAMessages = [
        ['role' => 'user', 'content' => 'A prompt'],
        ['role' => 'assistant', 'content' => 'A reply'],
    ];

    expect([
        'new_conversation_snapshot' => $newConversationSnapshot,
        'review_conversation_ids' => DB::table('agent_conversations')
            ->where('pls_review_id', $review->getKey())
            ->where('user_id', auth()->id())
            ->pluck('id')
            ->all(),
        'new_conversation_messages' => convergenceMessagesFor($newConversationId),
        'messages_on_deleted_conversation' => DB::table('agent_conversation_messages')
            ->where('conversation_id', $deletedConversationId)
            ->count(),
        'messages_containing_b_exchange' => DB::table('agent_conversation_messages')
            ->where(fn ($query) => $query
                ->where('content', 'like', '%B prompt%')
                ->orWhere('content', 'like', '%B reply%'))
            ->count(),
        'page_b_error' => $pageB->get('assistantError'),
        'page_b_conversation_id' => $pageB->get('assistantConversationId'),
    ])->toBe([
        'new_conversation_snapshot' => $tabAMessages,
        'review_conversation_ids' => [$newConversationId],
        'new_conversation_messages' => $tabAMessages,
        'messages_on_deleted_conversation' => 0,
        'messages_containing_b_exchange' => 0,
        'page_b_error' => CONVERGENCE_RESET_NOTICE,
        'page_b_conversation_id' => $newConversationId,
    ])
        ->and($pageB->get('assistantMessages'))->toEqual($tabAMessages);

    Exceptions::assertNothingReported();
});

test('case F: the client cannot change the held conversation id', function () {
    $review = plsReview(['title' => 'Convergence case F']);
    $otherUser = User::factory()->create();
    $otherConversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $otherConversationId,
        'user_id' => $otherUser->id,
        'title' => 'Other user conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pageB = convergenceMountSidebar($review);

    expect(fn () => $pageB->set('assistantConversationId', $otherConversationId))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('case G: a first send makes a single provider call and the SDK writes nothing', function () {
    $review = plsReview(['title' => 'Convergence case G']);
    $recorder = convergenceInsertRecorder();
    $providerCalls = 0;
    $insertCountAtFirstProviderCall = null;

    ReviewAssistantAgent::fake(function () use (&$providerCalls, &$insertCountAtFirstProviderCall, $recorder): string {
        $providerCalls++;
        $insertCountAtFirstProviderCall ??= $recorder->count();

        return 'Single reply';
    });

    convergenceMountSidebar($review)
        ->assertSet('assistantConversationId', null)
        ->call('sendPrompt', 'First prompt');

    expect([
        'provider_calls' => $providerCalls,
        'conversation_inserts_total' => convergenceInsertCount($recorder, 'agent_conversations'),
        'conversation_inserts_after_provider_call' => convergenceInsertCount($recorder, 'agent_conversations', $insertCountAtFirstProviderCall),
        'message_inserts_before_provider_call' => convergenceInsertCount($recorder, 'agent_conversation_messages', 0, $insertCountAtFirstProviderCall),
        'message_inserts_after_provider_call' => convergenceInsertCount($recorder, 'agent_conversation_messages', $insertCountAtFirstProviderCall),
    ])->toBe([
        'provider_calls' => 1,
        'conversation_inserts_total' => 1,
        'conversation_inserts_after_provider_call' => 0,
        'message_inserts_before_provider_call' => 0,
        'message_inserts_after_provider_call' => 2,
    ]);
});
