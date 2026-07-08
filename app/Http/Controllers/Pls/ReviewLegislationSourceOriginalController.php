<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewLegislationSourceOriginalController extends Controller
{
    public function __invoke(PlsReview $review, Document $document): StreamedResponse
    {
        Gate::authorize('view', $review);

        abort_unless(
            (int) $document->pls_review_id === (int) $review->getKey()
            && $document->document_type === DocumentType::LegislationText,
            404,
        );

        $storagePath = trim($document->storage_path);
        $disk = Storage::disk((string) data_get($document->metadata, 'disk', config('filesystems.default')));

        abort_if($storagePath === '' || ! $disk->exists($storagePath), 404);

        $stream = $disk->readStream($storagePath);

        abort_if($stream === false, 404);

        $filename = basename(str_replace('\\', '/', trim((string) data_get($document->metadata, 'original_name', '')) ?: basename($storagePath)));
        $fallbackFilename = trim((string) preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($filename))) ?: 'legislation-source';

        $headers = [
            'Content-Type' => $document->mime_type ?: ($disk->mimeType($storagePath) ?: 'application/octet-stream'),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename, $fallbackFilename),
        ];

        if ($document->file_size !== null) {
            $headers['Content-Length'] = (string) $document->file_size;
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
