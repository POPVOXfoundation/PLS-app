<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pls_review_memberships')
            ->where('role', 'editor')
            ->update(['role' => 'contributor']);
    }

    public function down(): void
    {
        DB::table('pls_review_memberships')
            ->where('role', 'contributor')
            ->update(['role' => 'editor']);
    }
};
