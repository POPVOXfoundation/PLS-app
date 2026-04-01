<?php

use App\Domain\Documents\AssistantSourceDocument;
use App\Support\PlsAssistant\TextractPdfExtractor;
use Aws\Result;
use Aws\Textract\TextractClient;

test('textract extractor starts an async job for an s3-backed stored PDF', function () {
    config()->set('filesystems.disks.assistant-s3', [
        'driver' => 's3',
        'key' => 'test',
        'secret' => 'test',
        'region' => 'us-east-1',
        'bucket' => 'assistant-bucket',
        'prefix' => 'assistant-sources',
    ]);

    $document = AssistantSourceDocument::factory()->create([
        'storage_path' => 'imports/wfd/guide.pdf',
        'metadata' => [
            'disk' => 'assistant-s3',
        ],
    ]);

    $client = Mockery::mock(TextractClient::class);
    $client->shouldReceive('startDocumentTextDetection')
        ->once()
        ->with(Mockery::on(function (array $payload): bool {
            return data_get($payload, 'DocumentLocation.S3Object.Bucket') === 'assistant-bucket'
                && data_get($payload, 'DocumentLocation.S3Object.Name') === 'assistant-sources/imports/wfd/guide.pdf';
        }))
        ->andReturn(new Result([
            'JobId' => 'job-123',
        ]));

    $result = (new TextractPdfExtractor($client, pollDelaySeconds: 12))->extract($document);

    expect($result->status)->toBe('processing')
        ->and($result->driver)->toBe('textract')
        ->and($result->metadata['textract_job_id'])->toBe('job-123')
        ->and($result->pollAfterSeconds)->toBe(12);
});

test('textract extractor finalizes paginated line output when polling succeeds', function () {
    config()->set('filesystems.disks.assistant-s3', [
        'driver' => 's3',
        'key' => 'test',
        'secret' => 'test',
        'region' => 'us-east-1',
        'bucket' => 'assistant-bucket',
    ]);

    $document = AssistantSourceDocument::factory()->create([
        'metadata' => [
            'disk' => 'assistant-s3',
            'extraction' => [
                'textract_job_id' => 'job-123',
            ],
        ],
    ]);

    $client = Mockery::mock(TextractClient::class);
    $client->shouldReceive('getDocumentTextDetection')
        ->once()
        ->with(['JobId' => 'job-123'])
        ->andReturn(new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'LINE', 'Text' => 'Line one'],
            ],
            'NextToken' => 'page-2',
        ]));
    $client->shouldReceive('getDocumentTextDetection')
        ->once()
        ->with(['JobId' => 'job-123', 'NextToken' => 'page-2'])
        ->andReturn(new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'LINE', 'Text' => 'Line two'],
            ],
        ]));

    $result = (new TextractPdfExtractor($client))->extract($document);

    expect($result->status)->toBe('completed')
        ->and($result->content)->toBe("Line one\nLine two")
        ->and($result->metadata['textract_job_id'])->toBe('job-123');
});

test('textract extractor reports failed polling responses', function () {
    config()->set('filesystems.disks.assistant-s3', [
        'driver' => 's3',
        'key' => 'test',
        'secret' => 'test',
        'region' => 'us-east-1',
        'bucket' => 'assistant-bucket',
    ]);

    $document = AssistantSourceDocument::factory()->create([
        'metadata' => [
            'disk' => 'assistant-s3',
            'extraction' => [
                'textract_job_id' => 'job-123',
            ],
        ],
    ]);

    $client = Mockery::mock(TextractClient::class);
    $client->shouldReceive('getDocumentTextDetection')
        ->once()
        ->with(['JobId' => 'job-123'])
        ->andReturn(new Result([
            'JobStatus' => 'FAILED',
            'StatusMessage' => 'Unsupported document',
        ]));

    $result = (new TextractPdfExtractor($client))->extract($document);

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('Unsupported document');
});
