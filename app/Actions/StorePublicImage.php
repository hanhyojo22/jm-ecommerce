<?php

namespace App\Actions;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StorePublicImage
{
    /**
     * Neon controls public access at the bucket level and rejects object ACLs.
     */
    public function handle(UploadedFile $file, string $directory, string $diskName): string
    {
        $disk = Storage::disk($diskName);

        if ($disk instanceof AwsS3V3Adapter) {
            $path = $file->hashName($directory);
            $disk->getClient()->putObject([
                'Bucket' => $disk->getConfig()['bucket'],
                'Key' => $path,
                'SourceFile' => $file->getPathname(),
                'ContentType' => $file->getMimeType(),
            ]);

            return $path;
        }

        return $file->store($directory, $diskName)
            ?: throw new RuntimeException('Image storage failed.');
    }
}
