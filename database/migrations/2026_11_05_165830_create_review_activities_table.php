<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('review_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
            $table->timestamps();
            $table->index(['pls_review_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_activities');
    }
};
