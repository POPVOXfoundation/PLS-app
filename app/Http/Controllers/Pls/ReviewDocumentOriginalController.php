<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewDocumentOriginalController extends Controller
{
    public function __invoke(PlsReview $review, Document $document): StreamedResponse
    {
        Gate::authorize('view', $review);
        abort_unless((int) $document->pls_review_id === (int) $review->id, 404);

        $path = trim($document->storage_path);
        $disk = Storage::disk((string) data_get($document->metadata, 'disk', config('filesystems.default')));
        abort_if($path === '' || ! $disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_if($stream === false, 404);

        $filename = basename(str_replace('\\', '/', trim((string) data_get($document->metadata, 'original_name', '')) ?: basename($path)));
        $fallback = trim((string) preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($filename))) ?: 'document';
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $disposition = in_array($mime, ['application/pdf', 'text/plain'], true)
            ? HeaderUtils::DISPOSITION_INLINE
            : HeaderUtils::DISPOSITION_ATTACHMENT;

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, $fallback),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
