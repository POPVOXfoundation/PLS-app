<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pls_review_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number');
            $table->string('step_key', 64);
            $table->string('status', 32);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pls_review_id', 'step_number']);
            $table->unique(['pls_review_id', 'step_key']);
            $table->index(['pls_review_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pls_review_steps');
    }
};
