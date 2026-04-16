<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BroadcastImageService
{
    private const STORAGE_DIR = 'broadcasts';
    private const NORMALIZED_STORAGE_DIR = 'broadcasts/__normalized';
    private const SCALE_FACTOR = 0.85; // reduce resolution by 15%
    private const QUALITY = 75;
    private const ACCEPTED_UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif'];
    private const ACCEPTED_UPLOAD_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp',
        'image/avif',
    ];

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

    public function resolveServingPath(string $path): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return $path;
        }

        $mime = strtolower((string) ($disk->mimeType($path) ?: ''));
        if (! $this->shouldCreateNormalizedVariant($mime)) {
            return $path;
        }

        $normalizedPath = $this->normalizedVariantPath($path, $mime);
        if ($disk->exists($normalizedPath)) {
            return $normalizedPath;
        }

        if ($this->createNormalizedVariant($path, $normalizedPath, $mime)) {
            return $normalizedPath;
        }

        return $path;
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

    public static function supportsUpload(UploadedFile $file): bool
    {
        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $extension = strtolower((string) ($file->guessExtension() ?: $file->extension() ?: $file->getClientOriginalExtension() ?: ''));

        if ($mime !== '' && in_array($mime, self::ACCEPTED_UPLOAD_MIME_TYPES, true)) {
            return true;
        }

        return $extension !== '' && in_array($extension, self::ACCEPTED_UPLOAD_EXTENSIONS, true);
    }

    /**
     * @return list<string>
     */
    public static function acceptedUploadExtensions(): array
    {
        return self::ACCEPTED_UPLOAD_EXTENSIONS;
    }

    public static function supportedFormatsLabel(): string
    {
        return 'JPG, PNG, WEBP, GIF, BMP, atau AVIF';
    }

    private function storeOriginal(UploadedFile $file): string
    {
        return $file->store(self::STORAGE_DIR, 'public');
    }

    private function shouldCreateNormalizedVariant(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/bmp', 'image/x-ms-bmp'], true);
    }

    private function normalizedVariantPath(string $path, string $mime): string
    {
        $disk = Storage::disk('public');
        $lastModified = (int) ($disk->lastModified($path) ?: 0);
        $size = (int) ($disk->size($path) ?: 0);
        $extension = in_array($mime, ['image/png'], true) ? 'png' : 'jpg';
        $hash = sha1($path.'|'.$lastModified.'|'.$size.'|'.$mime);

        return sprintf('%s/%s.%s', self::NORMALIZED_STORAGE_DIR, $hash, $extension);
    }

    private function createNormalizedVariant(string $path, string $normalizedPath, string $mime): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $disk = Storage::disk('public');
        $sourcePath = $disk->path($path);
        $targetPath = $disk->path($normalizedPath);
        $targetDirectory = dirname($targetPath);

        if (! is_dir($targetDirectory) && ! @mkdir($targetDirectory, 0777, true) && ! is_dir($targetDirectory)) {
            return false;
        }

        $format = $mime === 'image/png' ? 'png' : 'jpeg';
        $script = $this->normalizationPowerShellScript($sourcePath, $targetPath, $format);
        $process = new Process([
            'powershell',
            '-NoProfile',
            '-NonInteractive',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $script,
        ]);
        $process->setTimeout(20);
        $process->run();

        return $process->isSuccessful() && is_file($targetPath) && filesize($targetPath) > 0;
    }

    private function normalizationPowerShellScript(string $sourcePath, string $targetPath, string $format): string
    {
        $quotedSource = $this->quotePowerShellString($sourcePath);
        $quotedTarget = $this->quotePowerShellString($targetPath);
        $quotedFormat = $this->quotePowerShellString($format);

        return <<<POWERSHELL
Add-Type -AssemblyName System.Drawing
\$source = '$quotedSource'
\$target = '$quotedTarget'
\$format = '$quotedFormat'
\$image = \$null
\$bitmap = \$null
\$graphics = \$null
\$encoderParams = \$null
try {
    \$image = [System.Drawing.Image]::FromFile(\$source)

    if (\$format -eq 'jpeg') {
        \$bitmap = New-Object System.Drawing.Bitmap \$image.Width, \$image.Height, ([System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
        \$graphics = [System.Drawing.Graphics]::FromImage(\$bitmap)
        \$graphics.Clear([System.Drawing.Color]::White)
        \$graphics.DrawImage(\$image, 0, 0, \$image.Width, \$image.Height)

        \$codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { \$_.MimeType -eq 'image/jpeg' } | Select-Object -First 1
        \$encoder = [System.Drawing.Imaging.Encoder]::Quality
        \$encoderParams = New-Object System.Drawing.Imaging.EncoderParameters 1
        \$encoderParams.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter(\$encoder, [long]85)
        \$bitmap.Save(\$target, \$codec, \$encoderParams)
    } else {
        \$bitmap = New-Object System.Drawing.Bitmap \$image
        \$bitmap.Save(\$target, [System.Drawing.Imaging.ImageFormat]::Png)
    }
} finally {
    if (\$encoderParams -ne \$null) { \$encoderParams.Dispose() }
    if (\$graphics -ne \$null) { \$graphics.Dispose() }
    if (\$bitmap -ne \$null) { \$bitmap.Dispose() }
    if (\$image -ne \$null) { \$image.Dispose() }
}
POWERSHELL;
    }

    private function quotePowerShellString(string $value): string
    {
        return str_replace("'", "''", $value);
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
