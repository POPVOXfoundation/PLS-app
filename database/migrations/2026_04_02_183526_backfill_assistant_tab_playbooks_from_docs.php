<?php

use App\Domain\Assistant\Actions\ImportAssistantTabPlaybooksFromDocs;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(ImportAssistantTabPlaybooksFromDocs::class)->handle();
    }

    public function down(): void
    {
        //
    }
};
