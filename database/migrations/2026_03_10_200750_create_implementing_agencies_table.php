<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementing_agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('name');
            $table->string('agency_type', 32);
            $table->timestamps();

            $table->index(['pls_review_id', 'agency_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementing_agencies');
    }
};
