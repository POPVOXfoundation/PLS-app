<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('short_title')->nullable();
            $table->string('legislation_type', 32);
            $table->date('date_enacted')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['jurisdiction_id', 'legislation_type']);
            $table->index('date_enacted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation');
    }
};
