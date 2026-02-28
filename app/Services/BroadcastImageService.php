<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BroadcastImageService
{
    private const STORAGE_DIR = 'broadcasts';
    private const SCALE_FACTOR = 0.85; // reduce resolution by 15%
    private const QUALITY = 75;

    public function storeOptimized(UploadedFile $file): string
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $realPath = $file->getRealPath();

        if ($realPath === false || ! $this->isSupportedMime($mime)) {
            return $this->storeOriginal($file);
        }

        $source = null;
        $canvas = null;

        try {
            $source = $this->createSourceImage($realPath, $mime);
            if (! $source) {
                return $this->storeOriginal($file);
            }

            $source = $this->fixOrientationIfNeeded($source, $realPath, $mime);
            $canvas = $this->resizeImage($source);
            if (! $canvas) {
                return $this->storeOriginal($file);
            }

            [$binary, $extension] = $this->encodeImage($canvas);
            if ($binary === null || $extension === null) {
                return $this->storeOriginal($file);
            }

            $optimizedPath = $this->buildPath($extension);
            $stored = Storage::disk('public')->put($optimizedPath, $binary, ['visibility' => 'public']);
            if (! $stored) {
                return $this->storeOriginal($file);
            }

            return $optimizedPath;
        } catch (\Throwable) {
            return $this->storeOriginal($file);
        } finally {
            $this->destroyImage($canvas);
            $this->destroyImage($source);
        }
    }

    public function delete(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        Storage::disk('public')->delete((string) $path);
    }

    private function buildPath(string $extension): string
    {
        return sprintf(
            '%s/%s/%s.%s',
            self::STORAGE_DIR,
            now()->format('Y/m'),
            Str::uuid(),
            $extension
        );
    }

    private function isSupportedMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    private function storeOriginal(UploadedFile $file): string
    {
        return $file->store(self::STORAGE_DIR, 'public');
    }

    private function createSourceImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false,
            default => false,
        };
    }

    private function fixOrientationIfNeeded($image, string $path, string $mime)
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = @imagerotate($image, $angle, 0);
        if (! $rotated) {
            return $image;
        }

        $this->destroyImage($image);

        return $rotated;
    }

    private function resizeImage($source)
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $newWidth = max(1, (int) floor($width * self::SCALE_FACTOR));
        $newHeight = max(1, (int) floor($height * self::SCALE_FACTOR));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if (! $canvas) {
            return false;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function encodeImage($canvas): array
    {
        if (function_exists('imagewebp')) {
            ob_start();
            $ok = imagewebp($canvas, null, self::QUALITY);
            $binary = ob_get_clean();

            if ($ok && $binary !== false) {
                return [$binary, 'webp'];
            }
        }

        return [null, null];
    }

    private function destroyImage($image): void
    {
        if ($image instanceof \GdImage || is_resource($image)) {
            imagedestroy($image);
        }
    }
}
