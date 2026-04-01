<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;
use Aws\Result;
use Aws\Textract\TextractClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TextractPdfExtractor implements AssistantSourceTextExtractor
{
    public function __construct(
        private readonly TextractClient $client,
        private readonly ?string $bucket = null,
        private readonly ?string $roleArn = null,
        private readonly ?string $snsTopicArn = null,
        private readonly int $pollDelaySeconds = 15,
    ) {}

    public function extract(AssistantSourceDocument $document): AssistantSourceExtractionResult
    {
        try {
            $diskName = $this->documentDisk($document);

            if ((string) config("filesystems.disks.{$diskName}.driver") !== 's3') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'textract',
                    method: $this->method(),
                    error: sprintf('Textract requires an s3-backed disk. [%s] is not configured as s3.', $diskName),
                );
            }

            $bucket = $this->bucket ?: (string) config("filesystems.disks.{$diskName}.bucket");

            if ($bucket === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'textract',
                    method: $this->method(),
                    error: sprintf('Textract bucket is not configured for disk [%s].', $diskName),
                );
            }

            $jobId = trim((string) data_get($document->metadata, 'extraction.textract_job_id', ''));

            if ($jobId === '') {
                $response = $this->client->startDocumentTextDetection($this->startPayload($bucket, $diskName, $document));

                return AssistantSourceExtractionResult::processing(
                    driver: 'textract',
                    method: $this->method(),
                    metadata: [
                        'textract_job_id' => (string) $response->get('JobId'),
                    ],
                    pollAfterSeconds: $this->pollDelaySeconds,
                );
            }

            return $this->pollDocument($jobId);
        } catch (Throwable $exception) {
            return AssistantSourceExtractionResult::failed(
                driver: 'textract',
                method: $this->method(),
                error: $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function startPayload(string $bucket, string $diskName, AssistantSourceDocument $document): array
    {
        $payload = [
            'DocumentLocation' => [
                'S3Object' => [
                    'Bucket' => $bucket,
                    'Name' => Storage::disk($diskName)->path($document->storage_path),
                ],
            ],
            'ClientRequestToken' => sha1($document->getKey().'|'.$document->updated_at?->toIso8601String().'|'.$document->storage_path),
            'JobTag' => Str::limit(Str::slug($document->title), 64, ''),
        ];

        if (filled($this->roleArn) && filled($this->snsTopicArn)) {
            $payload['NotificationChannel'] = [
                'RoleArn' => $this->roleArn,
                'SNSTopicArn' => $this->snsTopicArn,
            ];
        }

        return $payload;
    }

    private function pollDocument(string $jobId): AssistantSourceExtractionResult
    {
        $nextToken = null;
        $statusMessage = null;
        $lines = collect();

        do {
            $payload = ['JobId' => $jobId];

            if ($nextToken !== null) {
                $payload['NextToken'] = $nextToken;
            }

            /** @var Result $response */
            $response = $this->client->getDocumentTextDetection($payload);
            $status = (string) $response->get('JobStatus');
            $statusMessage = $response->get('StatusMessage');

            if ($status === 'FAILED') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'textract',
                    method: $this->method(),
                    error: trim((string) ($statusMessage ?: 'Textract document text detection failed.')),
                    metadata: [
                        'textract_job_id' => $jobId,
                    ],
                );
            }

            if ($status === 'IN_PROGRESS') {
                return AssistantSourceExtractionResult::processing(
                    driver: 'textract',
                    method: $this->method(),
                    metadata: [
                        'textract_job_id' => $jobId,
                    ],
                    pollAfterSeconds: $this->pollDelaySeconds,
                );
            }

            $lines = $lines->merge($this->lineBlocks($response));
            $nextToken = $response->get('NextToken');
        } while ($nextToken !== null);

        $content = $this->cleanText($lines->implode("\n"));

        if ($content === '') {
            return AssistantSourceExtractionResult::failed(
                driver: 'textract',
                method: $this->method(),
                error: 'Textract completed but returned no usable line text.',
                metadata: [
                    'textract_job_id' => $jobId,
                ],
            );
        }

        return AssistantSourceExtractionResult::completed(
            driver: 'textract',
            method: $this->method(),
            content: $content,
            metadata: [
                'textract_job_id' => $jobId,
                'status_message' => $statusMessage,
            ],
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function lineBlocks(Result $response): Collection
    {
        return collect($response->get('Blocks') ?: [])
            ->filter(fn (array $block): bool => ($block['BlockType'] ?? null) === 'LINE' && filled($block['Text'] ?? null))
            ->map(fn (array $block): string => trim((string) $block['Text']));
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function documentDisk(AssistantSourceDocument $document): string
    {
        $diskName = trim((string) data_get($document->metadata, 'disk', ''));

        if ($diskName === '') {
            throw new RuntimeException(sprintf('Assistant source [%s] does not define a storage disk.', $document->title));
        }

        return $diskName;
    }

    private function method(): string
    {
        return 'aws-textract document text detection';
    }
}
