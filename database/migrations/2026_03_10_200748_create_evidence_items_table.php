<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('title');
            $table->string('evidence_type', 32);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['pls_review_id', 'evidence_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_items');
    }
};
