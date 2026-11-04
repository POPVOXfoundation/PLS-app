<?php

namespace App\Support\PlsAssistant;

use App\Domain\Reviews\PlsReview;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewConversationResolver
{
    private const MAX_ATTEMPTS = 3;

    /**
     * Return the assistant conversation id for the given review and user, creating a
     * review-linked conversation when none exists.
     *
     * An existing conversation is returned as-is and never updated, so its title is kept.
     * A new conversation is inserted inside its own transaction (a savepoint when nested),
     * which commits before this method returns, so no lock is held during a later provider
     * call. The agent_conversations_review_user_unique constraint arbitrates concurrent
     * first sends: a unique violation is caught outside the transaction and the winning
     * row is re-read. If the winner disappeared in between, the lookup and insert are
     * retried, up to three attempts in total.
     *
     * @throws UniqueConstraintViolationException
     */
    public function resolve(PlsReview $review, int $userId, string $title, string $playbookVersion): string
    {
        for ($attempt = 1; ; $attempt++) {
            $existingConversationId = $this->findConversationId($review, $userId);

            if ($existingConversationId !== null) {
                return $existingConversationId;
            }

            try {
                return DB::transaction(function () use ($review, $userId, $title, $playbookVersion): string {
                    $conversationId = (string) Str::uuid7();
                    $now = now();

                    DB::table('agent_conversations')->insert([
                        'id' => $conversationId,
                        'user_id' => $userId,
                        'pls_review_id' => $review->getKey(),
                        'title' => $title,
                        'playbook_version' => $playbookVersion,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return $conversationId;
                });
            } catch (UniqueConstraintViolationException $exception) {
                $winningConversationId = $this->findConversationId($review, $userId);

                if ($winningConversationId !== null) {
                    return $winningConversationId;
                }

                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    private function findConversationId(PlsReview $review, int $userId): ?string
    {
        $conversationId = DB::table('agent_conversations')
            ->where('pls_review_id', $review->getKey())
            ->where('user_id', $userId)
            ->value('id');

        return $conversationId === null ? null : (string) $conversationId;
    }
}
