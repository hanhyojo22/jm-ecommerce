<?php

namespace Tests\Feature;

use App\Actions\StorePublicImage;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class StorePublicImageTest extends TestCase
{
    public function test_s3_upload_omits_object_acl_and_preserves_image_content_type(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'store-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
        );
        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('putObject')->once()->withArgs(function (array $parameters) use ($file): bool {
            $this->assertSame('product-images', $parameters['Bucket']);
            $this->assertStringStartsWith('store-logos/', $parameters['Key']);
            $this->assertSame($file->getPathname(), $parameters['SourceFile']);
            $this->assertSame('image/png', $parameters['ContentType']);
            $this->assertArrayNotHasKey('ACL', $parameters);

            return true;
        });
        $disk = Mockery::mock(AwsS3V3Adapter::class);
        $disk->shouldReceive('getClient')->once()->andReturn($client);
        $disk->shouldReceive('getConfig')->once()->andReturn(['bucket' => 'product-images']);
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $path = app(StorePublicImage::class)->handle($file, 'store-logos', 'public');

        $this->assertStringStartsWith('store-logos/', $path);
    }
}
