<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('recommendation_type', 32);
            $table->timestamps();

            $table->index(['pls_review_id', 'recommendation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
