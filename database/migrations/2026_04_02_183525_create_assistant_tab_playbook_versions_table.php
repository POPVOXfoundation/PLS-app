<?php

use App\Domain\Assistant\AssistantTabPlaybook;
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
        Schema::create('assistant_tab_playbook_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(AssistantTabPlaybook::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('role');
            $table->text('intro');
            $table->json('objectives');
            $table->json('allowed_capabilities');
            $table->json('disallowed_capabilities');
            $table->json('suggested_prompts');
            $table->json('rules');
            $table->json('guardrails');
            $table->json('response_style');
            $table->text('change_note');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assistant_tab_playbook_id', 'version_number']);
        });

        Schema::table('assistant_tab_playbooks', function (Blueprint $table) {
            $table->foreign('active_version_id')
                ->references('id')
                ->on('assistant_tab_playbook_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_tab_playbooks', function (Blueprint $table) {
            $table->dropForeign(['active_version_id']);
        });

        Schema::dropIfExists('assistant_tab_playbook_versions');
    }
};
