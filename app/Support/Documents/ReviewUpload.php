<?php

namespace App\Support\Documents;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ReviewUpload
{
    /** @return resource */
    public static function open(TemporaryUploadedFile $upload)
    {
        $stream = $upload->readStream();
        if (! is_resource($stream)) {
            throw new RuntimeException('The temporary upload could not be read.');
        }

        return $stream;
    }

    public static function sha256(TemporaryUploadedFile $upload): string
    {
        $stream = self::open($upload);
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }

    public static function copyTo(TemporaryUploadedFile $upload, string $disk, string $directory): string
    {
        $path = $directory.'/'.TemporaryUploadedFile::generateHashName($upload);
        $stream = self::open($upload);
        try {
            if (! Storage::disk($disk)->put($path, $stream)) {
                throw new RuntimeException('The uploaded source could not be stored.');
            }
        } finally {
            fclose($stream);
        }

        return $path;
    }
}
