<?php

namespace Database\Seeders;

use App\Domain\Institutions\Actions\ImportDppInstitutionCatalog;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Enums\ReviewGroupType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use Illuminate\Database\Seeder;
use Throwable;

class PlsInstitutionSeeder extends Seeder
{
    public function run(ImportDppInstitutionCatalog $importer): void
    {
        try {
            $importer->import();
        } catch (Throwable $exception) {
            $this->command?->warn('DPP institution import failed, falling back to bundled institution seed data.');
            $this->seedFallbackInstitutionCatalog();
        }

        $belizeAssembly = $this->resolveNationalLegislature('BLZ', ['blz-parl', 'national-assembly']);
        $ugandaParliament = $this->resolveNationalLegislature('UGA', ['uga', 'parliament-of-uganda']);
        $usCongress = $this->ensureUnitedStatesCongress();
        $tennesseeAssembly = $this->ensureTennesseeAssembly($usCongress);

        $this->seedReviewGroup($belizeAssembly, 'Governance and Public Service Unit');
        $this->seedReviewGroup($belizeAssembly, 'Public Finance and Budget Office');
        $this->seedReviewGroup($ugandaParliament, 'Legal and Parliamentary Affairs Office');
        $this->seedReviewGroup($ugandaParliament, 'National Economy Task Force');
        $this->seedReviewGroup($usCongress, 'Oversight and Accountability Office');
        $this->seedReviewGroup($tennesseeAssembly, 'State and Local Government Office');
    }

    private function seedReviewGroup(Legislature $legislature, string $name): void
    {
        $legislature->loadMissing('jurisdiction.country');

        ReviewGroup::query()->updateOrCreate(
            [
                'legislature_id' => $legislature->id,
                'name' => $name,
                'type' => ReviewGroupType::Committee->value,
            ],
            [
                'country_id' => $legislature->jurisdiction->country_id,
                'jurisdiction_id' => $legislature->jurisdiction_id,
            ],
        );
    }

    private function resolveNationalLegislature(string $iso3, array $slugs): Legislature
    {
        $country = Country::query()->where('iso3', $iso3)->firstOrFail();
        $jurisdiction = Jurisdiction::query()
            ->where('country_id', $country->id)
            ->where('slug', 'national')
            ->firstOrFail();

        return Legislature::query()
            ->where('jurisdiction_id', $jurisdiction->id)
            ->whereIn('slug', $slugs)
            ->orderByRaw(sprintf(
                "case when slug = '%s' then 0 else 1 end",
                $slugs[0],
            ))
            ->firstOrFail();
    }

    private function ensureUnitedStatesCongress(): Legislature
    {
        $unitedStates = Country::query()->updateOrCreate(
            ['iso3' => 'USA'],
            [
                'name' => 'United States',
                'iso2' => 'US',
                'default_locale' => 'en',
            ],
        );

        $usFederal = Jurisdiction::query()->updateOrCreate(
            ['country_id' => $unitedStates->id, 'slug' => 'federal'],
            [
                'name' => 'Federal',
                'jurisdiction_type' => JurisdictionType::Federal,
                'parent_id' => null,
            ],
        );

        return Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $usFederal->id, 'slug' => 'united-states-congress'],
            [
                'name' => 'United States Congress',
                'legislature_type' => LegislatureType::Congress,
                'description' => 'Federal legislature of the United States.',
            ],
        );
    }

    private function ensureTennesseeAssembly(Legislature $usCongress): Legislature
    {
        $usFederal = $usCongress->jurisdiction()->firstOrFail();

        $tennessee = Jurisdiction::query()->updateOrCreate(
            ['country_id' => $usFederal->country_id, 'slug' => 'tennessee'],
            [
                'name' => 'Tennessee',
                'jurisdiction_type' => JurisdictionType::State,
                'parent_id' => $usFederal->id,
            ],
        );

        return Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $tennessee->id, 'slug' => 'tennessee-general-assembly'],
            [
                'name' => 'Tennessee General Assembly',
                'legislature_type' => LegislatureType::Assembly,
                'description' => 'State legislature of Tennessee.',
            ],
        );
    }

    private function seedFallbackInstitutionCatalog(): void
    {
        $belize = Country::query()->updateOrCreate(
            ['iso2' => 'BZ'],
            [
                'name' => 'Belize',
                'iso3' => 'BLZ',
                'default_locale' => 'en',
            ],
        );

        $uganda = Country::query()->updateOrCreate(
            ['iso2' => 'UG'],
            [
                'name' => 'Uganda',
                'iso3' => 'UGA',
                'default_locale' => 'en',
            ],
        );

        $belizeNational = Jurisdiction::query()->updateOrCreate(
            ['country_id' => $belize->id, 'slug' => 'national'],
            [
                'name' => 'National',
                'jurisdiction_type' => JurisdictionType::National,
                'parent_id' => null,
            ],
        );

        $ugandaNational = Jurisdiction::query()->updateOrCreate(
            ['country_id' => $uganda->id, 'slug' => 'national'],
            [
                'name' => 'National',
                'jurisdiction_type' => JurisdictionType::National,
                'parent_id' => null,
            ],
        );

        Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $belizeNational->id, 'slug' => 'national-assembly'],
            [
                'name' => 'National Assembly',
                'legislature_type' => LegislatureType::Assembly,
                'description' => 'Bicameral legislature of Belize.',
            ],
        );

        Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $ugandaNational->id, 'slug' => 'parliament-of-uganda'],
            [
                'name' => 'Parliament of Uganda',
                'legislature_type' => LegislatureType::Parliament,
                'description' => 'National legislature of Uganda.',
            ],
        );
    }
}
