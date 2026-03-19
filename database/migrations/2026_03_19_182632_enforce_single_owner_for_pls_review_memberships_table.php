<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pls_reviews') || ! Schema::hasTable('pls_review_memberships')) {
            return;
        }

        DB::table('pls_reviews')
            ->orderBy('id')
            ->select(['id', 'created_by', 'created_at', 'updated_at'])
            ->each(function (object $review): void {
                $memberships = DB::table('pls_review_memberships')
                    ->where('pls_review_id', $review->id)
                    ->orderBy('id')
                    ->get(['id', 'user_id', 'role', 'created_at', 'updated_at']);

                $ownerUserId = $this->resolveOwnerUserId($review, $memberships);

                if ($ownerUserId === null) {
                    return;
                }

                DB::table('pls_review_memberships')
                    ->where('pls_review_id', $review->id)
                    ->where('role', 'owner')
                    ->where('user_id', '!=', $ownerUserId)
                    ->update([
                        'role' => 'editor',
                        'updated_at' => $review->updated_at,
                    ]);

                $canonicalMembership = $memberships->firstWhere('user_id', $ownerUserId);

                if ($canonicalMembership === null) {
                    DB::table('pls_review_memberships')->insert([
                        'pls_review_id' => $review->id,
                        'user_id' => $ownerUserId,
                        'role' => 'owner',
                        'invited_by' => null,
                        'created_at' => $review->created_at,
                        'updated_at' => $review->updated_at,
                    ]);
                } else {
                    DB::table('pls_review_memberships')
                        ->where('id', $canonicalMembership->id)
                        ->update([
                            'role' => 'owner',
                            'updated_at' => $review->updated_at,
                        ]);
                }

                if ($review->created_by !== $ownerUserId) {
                    DB::table('pls_reviews')
                        ->where('id', $review->id)
                        ->update([
                            'created_by' => $ownerUserId,
                        ]);
                }
            });

        DB::statement("create unique index if not exists pls_review_memberships_single_owner on pls_review_memberships (pls_review_id) where role = 'owner'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('pls_review_memberships')) {
            return;
        }

        DB::statement('drop index if exists pls_review_memberships_single_owner');
    }

    private function resolveOwnerUserId(object $review, Collection $memberships): ?int
    {
        if ($review->created_by !== null) {
            return (int) $review->created_by;
        }

        $ownerMembership = $memberships->firstWhere('role', 'owner');

        if ($ownerMembership !== null) {
            return (int) $ownerMembership->user_id;
        }

        $firstMembership = $memberships->first();

        return $firstMembership === null ? null : (int) $firstMembership->user_id;
    }
};
