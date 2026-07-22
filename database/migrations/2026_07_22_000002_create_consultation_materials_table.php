<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stakeholder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material_type', 50);
            $table->timestamps();

            $table->unique(['consultation_id', 'document_id']);
            $table->index(['consultation_id', 'material_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_materials');
    }
};
