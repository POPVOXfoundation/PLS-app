<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_source_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('scope', 32);
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('jurisdiction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('legislature_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pls_review_id')->nullable()->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('storage_path');
            $table->index(['scope', 'country_id', 'jurisdiction_id', 'legislature_id'], 'assistant_source_documents_scope_context_index');
            $table->index(['scope', 'pls_review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_source_documents');
    }
};
