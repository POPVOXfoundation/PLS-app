<?php

namespace App\Console\Commands;

use App\Support\PlsAssistant\WfdAssistantSourceImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportWfdAssistantSources extends Command
{
    protected $signature = 'pls:assistant-sources:import-wfd
        {--guide-path= : Local path for bootstrapping the 2017 WFD guide into storage}
        {--manual-path= : Local path for bootstrapping the 2023 WFD manual into storage}';

    protected $description = 'Import the WFD global assistant source PDFs into stored grounding records';

    public function handle(WfdAssistantSourceImporter $importer): int
    {
        $this->components->info('Importing WFD global assistant source documents...');

        try {
            foreach ($importer->importConfiguredSources([
                'guide' => $this->option('guide-path'),
                'manual' => $this->option('manual-path'),
            ]) as $result) {
                $this->line(sprintf(
                    '[%s] %s',
                    strtoupper($result['status']),
                    $result['title'],
                ));
                $this->line(sprintf('  Stored file: %s:%s', $result['disk'], $result['storage_path']));
                $this->line(sprintf('  Extractor: %s', $result['extraction_method']));
                $this->line(sprintf('  Extraction status: %s', strtoupper($result['extraction_status'])));
                $this->line(sprintf('  Current content length: %s characters', number_format($result['content_length'])));
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('WFD global assistant source import complete.');

        return self::SUCCESS;
    }
}
