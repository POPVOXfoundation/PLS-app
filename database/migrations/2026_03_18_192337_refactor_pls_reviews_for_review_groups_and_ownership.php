<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pls_reviews', function (Blueprint $table) {
            $table->foreignId('review_group_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('country_id')->constrained('users')->nullOnDelete();
            $table->unique(['created_by', 'slug']);
            $table->index(['review_group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pls_reviews', function (Blueprint $table) {
            $table->dropIndex(['review_group_id', 'status']);
            $table->dropUnique(['created_by', 'slug']);
            $table->dropConstrainedForeignId('review_group_id');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
