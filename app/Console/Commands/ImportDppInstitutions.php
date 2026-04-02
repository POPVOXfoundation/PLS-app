<?php

namespace App\Console\Commands;

use App\Domain\Institutions\Actions\ImportDppInstitutionCatalog;
use Illuminate\Console\Command;
use Throwable;

class ImportDppInstitutions extends Command
{
    protected $signature = 'pls:institutions:import-dpp
        {--connection=dpp_import : Source database connection name}';

    protected $description = 'Import countries and national governing bodies from the DPP catalog';

    public function handle(ImportDppInstitutionCatalog $importer): int
    {
        $this->components->info('Importing the DPP institution catalog...');

        try {
            $stats = $importer->import((string) $this->option('connection'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('Countries synced: %s', number_format($stats['countries'])));
        $this->line(sprintf('National jurisdictions synced: %s', number_format($stats['jurisdictions'])));
        $this->line(sprintf('National legislatures synced: %s', number_format($stats['legislatures'])));

        $this->components->info('DPP institution import complete.');

        return self::SUCCESS;
    }
}
