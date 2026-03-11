<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pls_review_legislation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legislation_id')->constrained('legislation')->cascadeOnDelete();
            $table->string('relationship_type', 32);
            $table->timestamps();

            $table->unique(['pls_review_id', 'legislation_id']);
            $table->index('relationship_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pls_review_legislation');
    }
};
