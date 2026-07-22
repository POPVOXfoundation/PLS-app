<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stakeholders', function (Blueprint $table): void {
            $table->string('stakeholder_type', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stakeholders', function (Blueprint $table): void {
            $table->string('stakeholder_type', 32)->change();
        });
    }
};
