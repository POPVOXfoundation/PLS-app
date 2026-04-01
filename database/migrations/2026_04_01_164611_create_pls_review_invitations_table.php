<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pls_review_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pls_review_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['pls_review_id', 'email', 'accepted_at'], 'unique_pending_invite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pls_review_invitations');
    }
};
