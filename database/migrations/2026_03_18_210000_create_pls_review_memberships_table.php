<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pls_review_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pls_review_id', 'user_id']);
        });

        $memberships = DB::table('pls_reviews')
            ->whereNotNull('created_by')
            ->get(['id', 'created_by', 'created_at', 'updated_at'])
            ->map(static fn (object $review): array => [
                'pls_review_id' => $review->id,
                'user_id' => $review->created_by,
                'role' => 'owner',
                'invited_by' => null,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
            ])
            ->all();

        foreach (array_chunk($memberships, 500) as $chunk) {
            DB::table('pls_review_memberships')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pls_review_memberships');
    }
};
