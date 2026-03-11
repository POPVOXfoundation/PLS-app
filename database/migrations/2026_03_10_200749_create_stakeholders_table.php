<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained('pls_reviews')->cascadeOnDelete();
            $table->string('name');
            $table->string('stakeholder_type', 32);
            $table->json('contact_details')->nullable();
            $table->timestamps();

            $table->index(['pls_review_id', 'stakeholder_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stakeholders');
    }
};
