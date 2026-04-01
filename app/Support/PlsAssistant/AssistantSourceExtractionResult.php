<?php

namespace App\Support\PlsAssistant;

/**
 * @phpstan-type AssistantSourceExtractionMetadata array<string, mixed>
 */
final readonly class AssistantSourceExtractionResult
{
    /**
     * @param  AssistantSourceExtractionMetadata  $metadata
     */
    private function __construct(
        public string $status,
        public string $driver,
        public string $method,
        public ?string $content = null,
        public array $metadata = [],
        public ?string $error = null,
        public ?int $pollAfterSeconds = null,
    ) {}

    /**
     * @param  AssistantSourceExtractionMetadata  $metadata
     */
    public static function completed(
        string $driver,
        string $method,
        string $content,
        array $metadata = [],
    ): self {
        return new self(
            status: 'completed',
            driver: $driver,
            method: $method,
            content: $content,
            metadata: $metadata,
        );
    }

    /**
     * @param  AssistantSourceExtractionMetadata  $metadata
     */
    public static function processing(
        string $driver,
        string $method,
        array $metadata = [],
        ?int $pollAfterSeconds = null,
    ): self {
        return new self(
            status: 'processing',
            driver: $driver,
            method: $method,
            metadata: $metadata,
            pollAfterSeconds: $pollAfterSeconds,
        );
    }

    /**
     * @param  AssistantSourceExtractionMetadata  $metadata
     */
    public static function failed(
        string $driver,
        string $method,
        string $error,
        array $metadata = [],
    ): self {
        return new self(
            status: 'failed',
            driver: $driver,
            method: $method,
            metadata: $metadata,
            error: $error,
        );
    }
}
