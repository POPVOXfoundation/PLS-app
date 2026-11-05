<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->longText('content')->nullable();
            $table->json('content_versions')->nullable();
        });
        Schema::create('finding_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('passage');
            $table->string('relationship');
            $table->text('note')->nullable();
            $table->string('finding_fingerprint', 64);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_citations');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['content', 'content_versions']);
        });
    }
};
