<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('title');
            $table->string('finding_type', 32);
            $table->text('summary')->nullable();
            $table->longText('detail')->nullable();
            $table->timestamps();

            $table->index(['pls_review_id', 'finding_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
