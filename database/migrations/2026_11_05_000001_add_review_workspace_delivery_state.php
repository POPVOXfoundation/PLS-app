<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->string('storage_path')->nullable()->change());
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('generation_status')->nullable();
            $table->uuid('generation_token')->nullable();
            $table->unsignedBigInteger('generation_template_id')->nullable();
            $table->text('generation_error')->nullable();
            $table->timestamp('generation_started_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
        });
        Schema::table('pls_review_invitations', function (Blueprint $table): void {
            $table->string('mail_status')->default('unknown');
            $table->timestamp('mail_sent_at')->nullable();
            $table->timestamp('mail_queued_at')->nullable();
        });
        Schema::create('review_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pls_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
            $table->timestamps();
            $table->index(['pls_review_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_activities');
        Schema::table('reports', fn (Blueprint $table) => $table->dropColumn(['generation_status', 'generation_error', 'generation_started_at', 'generation_token', 'generation_template_id', 'revision']));
        Schema::table('pls_review_invitations', fn (Blueprint $table) => $table->dropColumn(['mail_status', 'mail_sent_at', 'mail_queued_at']));
    }
};
