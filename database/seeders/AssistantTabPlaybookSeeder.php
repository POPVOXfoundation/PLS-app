<?php

namespace Database\Seeders;

use App\Domain\Assistant\Actions\ImportAssistantTabPlaybooksFromDocs;
use Illuminate\Database\Seeder;

class AssistantTabPlaybookSeeder extends Seeder
{
    public function run(): void
    {
        app(ImportAssistantTabPlaybooksFromDocs::class)->handle();
    }
}
