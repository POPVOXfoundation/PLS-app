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
        Schema::table('reports', function (Blueprint $table) {
            $table->string('generation_status', 32)->nullable();
            $table->uuid('generation_token')->nullable();
            $table->text('generation_error')->nullable();
            $table->timestamp('generation_requested_at')->nullable();
            $table->foreignId('generation_template_id')->nullable()->constrained('documents')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generation_template_id');
            $table->dropColumn(['generation_status', 'generation_token', 'generation_error', 'generation_requested_at']);
        });
    }
};
