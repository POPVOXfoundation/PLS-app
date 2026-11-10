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
        foreach (['pls_review_invitations', 'pls_review_memberships'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('mail_status', 32)->default('unknown');
                $table->uuid('mail_token')->nullable();
                $table->timestamp('mail_requested_at')->nullable();
                $table->timestamp('mail_accepted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['pls_review_invitations', 'pls_review_memberships'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['mail_status', 'mail_token', 'mail_requested_at', 'mail_accepted_at']));
        }
    }
};
