<?php

namespace Tests\Feature\Pengumuman;

use App\Models\AffiliationBroadcast;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BroadcastImageRouteTest extends TestCase
{
    public function test_broadcast_image_accessor_returns_media_route_for_local_storage_paths(): void
    {
        $broadcast = new AffiliationBroadcast([
            'image_path' => 'storage/broadcasts/2026/04/example.jpg',
        ]);

        $this->assertSame(
            '/media/broadcasts/broadcasts/2026/04/example.jpg',
            $broadcast->image_url
        );
    }

    public function test_broadcast_image_route_streams_public_disk_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('broadcasts/2026/04/example.jpg', 'image-content');

        $response = $this->get('/media/broadcasts/broadcasts/2026/04/example.jpg');

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('cache-control', '');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);
        $this->assertSame('image-content', $response->streamedContent());
    }

    public function test_broadcast_image_route_rejects_paths_outside_broadcast_directory(): void
    {
        $this->get('/media/broadcasts/avatars/example.jpg')->assertNotFound();
        $this->get('/media/broadcasts/../.env')->assertNotFound();
    }
}
