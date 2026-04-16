<?php

namespace Tests\Unit\Services;

use App\Services\BroadcastImageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BroadcastImageServiceTest extends TestCase
{
    public function test_supports_common_web_safe_broadcast_image_formats(): void
    {
        $service = new BroadcastImageService();

        $jpeg = UploadedFile::fake()->createWithContent('poster.jpg', hex2bin('ffd8ffe000104a464946000101'));
        $png = UploadedFile::fake()->createWithContent('poster.png', hex2bin('89504e470d0a1a0a'));
        $bmp = UploadedFile::fake()->createWithContent('poster.bmp', 'BMplaceholder');
        $avif = UploadedFile::fake()->createWithContent('poster.avif', str_repeat("\x00", 4).'ftypavif');

        $this->assertTrue($service::supportsUpload($jpeg));
        $this->assertTrue($service::supportsUpload($png));
        $this->assertTrue($service::supportsUpload($bmp));
        $this->assertTrue($service::supportsUpload($avif));
    }

    public function test_rejects_non_supported_broadcast_image_formats(): void
    {
        $service = new BroadcastImageService();

        $svg = UploadedFile::fake()->createWithContent('poster.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $txt = UploadedFile::fake()->createWithContent('poster.txt', 'not-an-image');

        $this->assertFalse($service::supportsUpload($svg));
        $this->assertFalse($service::supportsUpload($txt));
    }
}
