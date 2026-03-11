<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('title');
            $table->string('report_type', 32);
            $table->string('status', 32);
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['pls_review_id', 'status']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
