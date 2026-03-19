<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pls_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jurisdiction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->unsignedTinyInteger('current_step_number')->default(1);
            $table->date('start_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_step_number']);
            $table->index(['country_id', 'jurisdiction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pls_reviews');
    }
};
