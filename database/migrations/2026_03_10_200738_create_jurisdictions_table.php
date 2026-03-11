<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('jurisdiction_type', 32);
            $table->foreignId('parent_id')->nullable()->constrained('jurisdictions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['country_id', 'slug']);
            $table->index(['country_id', 'jurisdiction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisdictions');
    }
};
