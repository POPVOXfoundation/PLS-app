<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->foreignId('pls_review_id')
                ->nullable()
                ->after('user_id')
                ->constrained('pls_reviews')
                ->nullOnDelete();
            $table->string('playbook_version')
                ->nullable()
                ->after('title');

            $table->unique(['pls_review_id', 'user_id'], 'agent_conversations_review_user_unique');
            $table->index(['pls_review_id', 'updated_at'], 'agent_conversations_review_updated_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropUnique('agent_conversations_review_user_unique');
            $table->dropIndex('agent_conversations_review_updated_index');
            $table->dropConstrainedForeignId('pls_review_id');
            $table->dropColumn('playbook_version');
        });
    }
};
