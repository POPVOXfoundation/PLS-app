<?php

namespace App\Support\PlsAssistant;

use Aws\Textract\TextractClient;
use RuntimeException;

class AssistantSourceTextExtractorFactory
{
    public function make(?string $driver = null): AssistantSourceTextExtractor
    {
        return match ($driver ?? $this->configuredDriver()) {
            'local' => new LocalPdfToTextExtractor(
                binary: (string) config('pls_assistant.assistant_sources.pdftotext_binary', 'pdftotext'),
            ),
            'textract' => new TextractPdfExtractor(
                client: new TextractClient($this->textractClientConfig()),
                bucket: $this->nullableString(config('pls_assistant.assistant_sources.textract.bucket')),
                roleArn: $this->nullableString(config('pls_assistant.assistant_sources.textract.role_arn')),
                snsTopicArn: $this->nullableString(config('pls_assistant.assistant_sources.textract.sns_topic_arn')),
                pollDelaySeconds: (int) config('pls_assistant.assistant_sources.textract.poll_delay_seconds', 15),
                fallbackExtractor: new LocalPdfToTextExtractor(
                    binary: (string) config('pls_assistant.assistant_sources.pdftotext_binary', 'pdftotext'),
                ),
            ),
            default => throw new RuntimeException(sprintf(
                'Unsupported assistant source extractor [%s].',
                $driver ?? $this->configuredDriver(),
            )),
        };
    }

    public function configuredDriver(): string
    {
        return (string) config('pls_assistant.assistant_sources.extractor', 'local');
    }

    /**
     * @return array<string, mixed>
     */
    private function textractClientConfig(): array
    {
        return array_filter([
            'version' => 'latest',
            'region' => (string) config('pls_assistant.assistant_sources.textract.region', 'us-east-1'),
            'endpoint' => $this->nullableString(config('pls_assistant.assistant_sources.textract.endpoint')),
            'use_path_style_endpoint' => filter_var(
                config('pls_assistant.assistant_sources.textract.use_path_style_endpoint', false),
                FILTER_VALIDATE_BOOL,
            ),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
