<?php

namespace App\Http\Controllers;

use App\Services\BroadcastImageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BroadcastImageController extends Controller
{
    public function __construct(
        private readonly BroadcastImageService $broadcastImageService
    ) {
    }

    public function __invoke(string $path): StreamedResponse
    {
        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

        if (
            $normalizedPath === ''
            || Str::contains($normalizedPath, ['../', '..\\'])
            || ! Str::startsWith($normalizedPath, 'broadcasts/')
        ) {
            abort(404);
        }

        $disk = Storage::disk('public');
        $servedPath = $this->broadcastImageService->resolveServingPath($normalizedPath);

        if (! $disk->exists($servedPath)) {
            abort(404);
        }

        return $disk->response($servedPath, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
