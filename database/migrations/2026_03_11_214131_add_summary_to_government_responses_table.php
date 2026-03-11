<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_responses', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('government_responses', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
