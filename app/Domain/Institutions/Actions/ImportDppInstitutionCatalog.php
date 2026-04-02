<?php

namespace App\Domain\Institutions\Actions;

use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ImportDppInstitutionCatalog
{
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * @return array{countries:int,jurisdictions:int,legislatures:int}
     */
    public function import(string $connection = 'dpp_import'): array
    {
        if (
            ! Schema::connection($connection)->hasTable('countries')
            || ! Schema::connection($connection)->hasTable('gov_bodies')
        ) {
            throw new RuntimeException("The [{$connection}] connection is missing the DPP institution tables.");
        }

        $source = $this->database->connection($connection);

        /** @var Collection<int, object> $sourceCountries */
        $sourceCountries = $source->table('countries')
            ->orderBy('name')
            ->get(['id', 'name', 'alpha2_code', 'alpha3_code']);

        /** @var Collection<int, object> $sourceGovBodies */
        $sourceGovBodies = $source->table('gov_bodies')
            ->orderBy('country_id')
            ->orderByRaw('coalesce(parent_id, id)')
            ->orderBy('chamber_order')
            ->orderBy('name')
            ->get([
                'country_id',
                'name',
                'code',
                'title',
                'parent_id',
                'chamber_order',
            ]);

        $countryMap = [];
        $jurisdictionMap = [];
        $stats = [
            'countries' => 0,
            'jurisdictions' => 0,
            'legislatures' => 0,
        ];

        foreach ($sourceCountries as $sourceCountry) {
            $country = Country::query()->updateOrCreate(
                ['iso3' => Str::upper((string) $sourceCountry->alpha3_code)],
                [
                    'name' => (string) $sourceCountry->name,
                    'iso2' => Str::upper((string) $sourceCountry->alpha2_code),
                    'default_locale' => 'en',
                ],
            );

            $jurisdiction = Jurisdiction::query()->updateOrCreate(
                [
                    'country_id' => $country->id,
                    'slug' => 'national',
                ],
                [
                    'name' => 'National',
                    'jurisdiction_type' => JurisdictionType::National,
                    'parent_id' => null,
                ],
            );

            $countryMap[(int) $sourceCountry->id] = $country;
            $jurisdictionMap[$country->id] = $jurisdiction;
            $stats['countries']++;
            $stats['jurisdictions']++;
        }

        foreach ($sourceGovBodies as $sourceGovBody) {
            $country = $countryMap[(int) $sourceGovBody->country_id] ?? null;

            if ($country === null) {
                continue;
            }

            $jurisdiction = $jurisdictionMap[$country->id];

            Legislature::query()->updateOrCreate(
                [
                    'jurisdiction_id' => $jurisdiction->id,
                    'slug' => (string) $sourceGovBody->code,
                ],
                [
                    'name' => (string) $sourceGovBody->name,
                    'legislature_type' => $this->determineLegislatureType(
                        (string) $sourceGovBody->name,
                        $sourceGovBody->title,
                    ),
                    'description' => $this->buildDescription($sourceGovBody),
                ],
            );

            $stats['legislatures']++;
        }

        return $stats;
    }

    private function determineLegislatureType(string $name, mixed $title): LegislatureType
    {
        $value = Str::lower(trim($name.' '.(is_string($title) ? $title : '')));

        if (Str::contains($value, 'congress')) {
            return LegislatureType::Congress;
        }

        if (Str::contains($value, 'assembly')) {
            return LegislatureType::Assembly;
        }

        if (Str::contains($value, 'parliament')) {
            return LegislatureType::Parliament;
        }

        return LegislatureType::Legislature;
    }

    private function buildDescription(object $sourceGovBody): string
    {
        $parts = array_filter([
            is_string($sourceGovBody->title) && $sourceGovBody->title !== '' ? $sourceGovBody->title : null,
            'Imported from the DPP governing body catalog.',
        ]);

        return implode(' ', $parts);
    }
}
