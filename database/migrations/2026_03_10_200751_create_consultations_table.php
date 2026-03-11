<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('title');
            $table->string('consultation_type', 32);
            $table->timestamp('held_at')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['pls_review_id', 'consultation_type']);
            $table->index('held_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
