<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->foreignId('stakeholder_id')->constrained('stakeholders')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['pls_review_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
