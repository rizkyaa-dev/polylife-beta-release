<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class ProfileAvatarImageService
{
    private const MIN_SIZE = 64;
    private const MAX_SIZE = 256;
    private const QUALITY = 75;

    /**
     * @return array{image: string, mime_type: string, width: int, height: int, size: int}
     */
    public function optimize(UploadedFile $file): array
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('Server belum mendukung encoding WebP.');
        }

        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $path = $file->getRealPath();

        if ($path === false || ! $this->isSupportedMime($mime)) {
            throw new RuntimeException('Format gambar tidak didukung.');
        }

        $source = null;
        $canvas = null;

        try {
            $source = $this->createSourceImage($path, $mime);
            if (! $source) {
                throw new RuntimeException('Gambar tidak bisa dibaca.');
            }

            $source = $this->fixOrientationIfNeeded($source, $path, $mime);
            $canvas = $this->cropAndResize($source);

            ob_start();
            $ok = imagewebp($canvas, null, self::QUALITY);
            $binary = ob_get_clean();

            if (! $ok || $binary === false || $binary === '') {
                throw new RuntimeException('Gambar tidak bisa dikompres.');
            }

            return [
                'image' => $binary,
                'mime_type' => 'image/webp',
                'width' => imagesx($canvas),
                'height' => imagesy($canvas),
                'size' => strlen($binary),
            ];
        } finally {
            $this->destroyImage($canvas);
            $this->destroyImage($source);
        }
    }

    private function isSupportedMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    private function createSourceImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
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

    private function cropAndResize($source)
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('Resolusi gambar tidak valid.');
        }

        $cropSize = min($width, $height);
        $sourceX = (int) floor(($width - $cropSize) / 2);
        $sourceY = (int) floor(($height - $cropSize) / 2);
        $targetSize = min(self::MAX_SIZE, max(self::MIN_SIZE, $cropSize));

        $canvas = imagecreatetruecolor($targetSize, $targetSize);
        if (! $canvas) {
            throw new RuntimeException('Gambar tidak bisa diproses.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetSize,
            $targetSize,
            $cropSize,
            $cropSize
        );

        return $canvas;
    }

    private function destroyImage($image): void
    {
        if ($image instanceof \GdImage || is_resource($image)) {
            imagedestroy($image);
        }
    }
}
