<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32);
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('jurisdiction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('legislature_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'name']);
            $table->index(['country_id', 'jurisdiction_id', 'legislature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_groups');
    }
};
