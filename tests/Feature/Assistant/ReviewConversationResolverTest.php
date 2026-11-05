<?php

use App\Models\User;
use App\Support\PlsAssistant\ReviewConversationResolver;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('returns the existing review conversation without updating it', function () {
    $review = plsReview();
    $user = User::factory()->create();
    $createdAt = now()->subDay()->startOfSecond();

    DB::table('agent_conversations')->insert([
        'id' => 'existing-conversation',
        'user_id' => $user->id,
        'pls_review_id' => $review->id,
        'title' => 'Original title',
        'playbook_version' => 'old-version',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $before = (array) DB::table('agent_conversations')->where('id', 'existing-conversation')->first();

    $conversationId = app(ReviewConversationResolver::class)
        ->resolve($review, $user->id, 'New title', 'new-version');

    expect($conversationId)->toBe('existing-conversation')
        ->and(DB::table('agent_conversations')->count())->toBe(1)
        ->and((array) DB::table('agent_conversations')->where('id', 'existing-conversation')->first())->toBe($before);
});

it('inserts a review-linked conversation when none exists and reuses it afterwards', function () {
    $review = plsReview();
    $user = User::factory()->create();
    $otherReview = plsReview();

    DB::table('agent_conversations')->insert([
        'id' => 'other-review-conversation',
        'user_id' => $user->id,
        'pls_review_id' => $otherReview->id,
        'title' => 'Other review',
        'playbook_version' => 'other-version',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $resolver = app(ReviewConversationResolver::class);
    $conversationId = $resolver->resolve($review, $user->id, 'First prompt title', 'playbook-v1');

    $row = DB::table('agent_conversations')->where('id', $conversationId)->first();

    expect($conversationId)->not->toBe('other-review-conversation')
        ->and($row)->not->toBeNull()
        ->and($row->user_id)->toBe($user->id)
        ->and($row->pls_review_id)->toBe($review->id)
        ->and($row->title)->toBe('First prompt title')
        ->and($row->playbook_version)->toBe('playbook-v1')
        ->and($row->created_at)->not->toBeNull()
        ->and($row->updated_at)->not->toBeNull()
        ->and($resolver->resolve($review, $user->id, 'Second prompt title', 'playbook-v2'))->toBe($conversationId)
        ->and(DB::table('agent_conversations')->where('pls_review_id', $review->id)->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('agent_conversations')->where('id', $conversationId)->value('title'))->toBe('First prompt title');
});

it('returns the competing conversation when one is inserted right after the lookup (simulated overlap)', function () {
    $review = plsReview();
    $user = User::factory()->create();
    $competitorCreatedAt = now()->subMinute()->startOfSecond();
    $hasInsertedCompetitor = false;

    // Simulated overlap: single process, SQLite in memory. A one-shot listener inserts the
    // competing linked row right after the resolver's lookup select returns no row.
    DB::listen(function (QueryExecuted $query) use (&$hasInsertedCompetitor, $review, $user, $competitorCreatedAt): void {
        if ($hasInsertedCompetitor) {
            return;
        }

        if (preg_match('/^select "id" from "agent_conversations" where "pls_review_id" = \? and "user_id" = \? limit 1$/', trim($query->sql)) !== 1) {
            return;
        }

        $hasInsertedCompetitor = true;

        DB::table('agent_conversations')->insert([
            'id' => 'competitor-conversation',
            'user_id' => $user->id,
            'pls_review_id' => $review->id,
            'title' => 'Competitor title',
            'playbook_version' => 'competitor-version',
            'created_at' => $competitorCreatedAt,
            'updated_at' => $competitorCreatedAt,
        ]);
    });

    $conversationId = app(ReviewConversationResolver::class)
        ->resolve($review, $user->id, 'Loser title', 'loser-version');

    $row = DB::table('agent_conversations')->where('id', 'competitor-conversation')->first();

    expect($hasInsertedCompetitor)->toBeTrue()
        ->and($conversationId)->toBe('competitor-conversation')
        ->and(DB::table('agent_conversations')->where('pls_review_id', $review->id)->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('agent_conversations')->count())->toBe(1)
        ->and($row->title)->toBe('Competitor title')
        ->and($row->playbook_version)->toBe('competitor-version')
        ->and(DB::table('agent_conversations')->whereNull('pls_review_id')->count())->toBe(0);
});
