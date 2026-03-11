<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('title');
            $table->string('document_type', 64);
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('storage_path');
            $table->index(['pls_review_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
