<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislation_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislation_id')->constrained('legislation')->cascadeOnDelete();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['pls_review_id', 'legislation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation_objectives');
    }
};
