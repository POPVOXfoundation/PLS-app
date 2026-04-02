<?php

use App\Domain\Institutions\Actions\ImportDppInstitutionCatalog;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('imports governing bodies regardless of active status', function () {
    $databasePath = tempnam(sys_get_temp_dir(), 'dpp-import-');

    expect($databasePath)->not->toBeFalse();

    try {
        Config::set('database.connections.dpp_import', [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('dpp_import');

        Schema::connection('dpp_import')->create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alpha2_code', 2);
            $table->string('alpha3_code', 3);
        });

        Schema::connection('dpp_import')->create('gov_bodies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->string('name');
            $table->string('code');
            $table->string('title')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('chamber_order')->nullable();
            $table->boolean('is_active')->default(true);
        });

        DB::connection('dpp_import')->table('countries')->insert([
            'id' => 1,
            'name' => 'Belize',
            'alpha2_code' => 'BZ',
            'alpha3_code' => 'BLZ',
        ]);

        DB::connection('dpp_import')->table('gov_bodies')->insert([
            [
                'country_id' => 1,
                'name' => 'National Assembly',
                'code' => 'blz-lower',
                'title' => null,
                'parent_id' => null,
                'chamber_order' => 1,
                'is_active' => false,
            ],
            [
                'country_id' => 1,
                'name' => 'Senate',
                'code' => 'blz-upper',
                'title' => null,
                'parent_id' => null,
                'chamber_order' => 2,
                'is_active' => true,
            ],
        ]);

        $stats = app(ImportDppInstitutionCatalog::class)->import('dpp_import');

        expect($stats)->toMatchArray([
            'countries' => 1,
            'jurisdictions' => 1,
            'legislatures' => 2,
        ]);

        expect(Country::query()->where('iso3', 'BLZ')->exists())->toBeTrue()
            ->and(Jurisdiction::query()->where('slug', 'national')->count())->toBe(1)
            ->and(Legislature::query()->where('slug', 'blz-lower')->exists())->toBeTrue()
            ->and(Legislature::query()->where('slug', 'blz-upper')->exists())->toBeTrue();
    } finally {
        DB::purge('dpp_import');

        if (is_string($databasePath) && file_exists($databasePath)) {
            unlink($databasePath);
        }
    }
});
