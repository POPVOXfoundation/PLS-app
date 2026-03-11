<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('response_status', 32);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['pls_review_id', 'response_status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_responses');
    }
};
