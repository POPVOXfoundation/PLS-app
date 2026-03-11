<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlsInstitutionSeeder::class,
            PlsReviewDemoSeeder::class,
        ]);
    }
}
