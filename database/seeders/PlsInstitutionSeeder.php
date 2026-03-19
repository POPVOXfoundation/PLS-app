<?php

namespace Database\Seeders;

use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Enums\ReviewGroupType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use Illuminate\Database\Seeder;

class PlsInstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $belize = Country::query()->updateOrCreate(
            ['iso2' => 'BZ'],
            [
                'name' => 'Belize',
                'iso3' => 'BLZ',
                'default_locale' => 'en_BZ',
            ],
        );

        $uganda = Country::query()->updateOrCreate(
            ['iso2' => 'UG'],
            [
                'name' => 'Uganda',
                'iso3' => 'UGA',
                'default_locale' => 'en_UG',
            ],
        );

        $unitedStates = Country::query()->updateOrCreate(
            ['iso2' => 'US'],
            [
                'name' => 'United States',
                'iso3' => 'USA',
                'default_locale' => 'en_US',
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

        $usFederal = Jurisdiction::query()->updateOrCreate(
            ['country_id' => $unitedStates->id, 'slug' => 'federal'],
            [
                'name' => 'Federal',
                'jurisdiction_type' => JurisdictionType::Federal,
                'parent_id' => null,
            ],
        );

        $tennessee = Jurisdiction::query()->updateOrCreate(
            ['country_id' => $unitedStates->id, 'slug' => 'tennessee'],
            [
                'name' => 'Tennessee',
                'jurisdiction_type' => JurisdictionType::State,
                'parent_id' => $usFederal->id,
            ],
        );

        $belizeAssembly = Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $belizeNational->id, 'slug' => 'national-assembly'],
            [
                'name' => 'National Assembly',
                'legislature_type' => LegislatureType::Assembly,
                'description' => 'Bicameral legislature of Belize.',
            ],
        );

        $ugandaParliament = Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $ugandaNational->id, 'slug' => 'parliament-of-uganda'],
            [
                'name' => 'Parliament of Uganda',
                'legislature_type' => LegislatureType::Parliament,
                'description' => 'National legislature of Uganda.',
            ],
        );

        $usCongress = Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $usFederal->id, 'slug' => 'united-states-congress'],
            [
                'name' => 'United States Congress',
                'legislature_type' => LegislatureType::Congress,
                'description' => 'Federal legislature of the United States.',
            ],
        );

        $tennesseeAssembly = Legislature::query()->updateOrCreate(
            ['jurisdiction_id' => $tennessee->id, 'slug' => 'tennessee-general-assembly'],
            [
                'name' => 'Tennessee General Assembly',
                'legislature_type' => LegislatureType::Assembly,
                'description' => 'State legislature of Tennessee.',
            ],
        );

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
}
