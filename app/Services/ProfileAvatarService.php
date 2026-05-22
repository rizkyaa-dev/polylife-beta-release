<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileAvatarService
{
    private const OUTPUT_SIZE = 256;
    private const MAX_SOURCE_DIMENSION = 4096;
    private const MAX_SOURCE_PIXELS = 16777216; // 4096 * 4096

    public function store(User $user, UploadedFile $file): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            $this->invalidAvatar('Server belum mendukung pemrosesan foto profil.');
        }

        $binary = $this->readFile($file);
        [$sourceWidth, $sourceHeight] = getimagesizefromstring($binary) ?: [0, 0];

        if ($sourceWidth < 64 || $sourceHeight < 64) {
            $this->invalidAvatar('Foto profil minimal berukuran 64 x 64 px.');
        }

        if (
            $sourceWidth > self::MAX_SOURCE_DIMENSION
            || $sourceHeight > self::MAX_SOURCE_DIMENSION
            || ($sourceWidth * $sourceHeight) > self::MAX_SOURCE_PIXELS
        ) {
            $this->invalidAvatar('Foto profil terlalu besar untuk diproses.');
        }

        $source = @imagecreatefromstring($binary);
        if (! $source) {
            $this->invalidAvatar('Foto profil tidak bisa diproses.');
        }

        try {
            $source = $this->fixOrientationIfNeeded($source, $file);
            [$avatarBinary, $mimeType] = $this->encodeAvatar($source);
        } finally {
            if ($source instanceof \GdImage || is_resource($source)) {
                imagedestroy($source);
            }
        }

        $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);
        if ($profile->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
            $profile->avatar_path = null;
            $profile->save();
        }

        $user->profileAvatar()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'image' => $avatarBinary,
                'mime_type' => $mimeType,
                'width' => self::OUTPUT_SIZE,
                'height' => self::OUTPUT_SIZE,
                'size' => strlen($avatarBinary),
            ]
        );
    }

    public function delete(User $user): void
    {
        $profile = $user->profile;

        $user->profileAvatar()->delete();

        if ($profile?->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
            $profile->avatar_path = null;
            $profile->save();
        }
    }

    private function readFile(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $binary = $path ? file_get_contents($path) : false;

        if ($binary === false || $binary === '') {
            $this->invalidAvatar('Foto profil tidak bisa dibaca.');
        }

        return $binary;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function encodeAvatar($source): array
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $side = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $side) / 2);
        $sourceY = (int) floor(($sourceHeight - $side) / 2);

        $canvas = imagecreatetruecolor(self::OUTPUT_SIZE, self::OUTPUT_SIZE);
        if (! $canvas) {
            $this->invalidAvatar('Foto profil tidak bisa diproses.');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            self::OUTPUT_SIZE,
            self::OUTPUT_SIZE,
            $side,
            $side
        );

        try {
            if (function_exists('imagewebp')) {
                ob_start();
                $ok = imagewebp($canvas, null, 75);
                $binary = ob_get_clean();

                if ($ok && is_string($binary) && $binary !== '') {
                    return [$binary, 'image/webp'];
                }
            }

            if (function_exists('imagejpeg')) {
                ob_start();
                $ok = imagejpeg($canvas, null, 85);
                $binary = ob_get_clean();

                if ($ok && is_string($binary) && $binary !== '') {
                    return [$binary, 'image/jpeg'];
                }
            }
        } finally {
            imagedestroy($canvas);
        }

        $this->invalidAvatar('Foto profil tidak bisa dikompres.');
    }

    private function fixOrientationIfNeeded($source, UploadedFile $file)
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $path = $file->getRealPath();

        if ($mime !== 'image/jpeg' || ! $path || ! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return $source;
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
            return $source;
        }

        $rotated = @imagerotate($source, $angle, 0);
        if (! $rotated) {
            return $source;
        }

        imagedestroy($source);

        return $rotated;
    }

    private function invalidAvatar(string $message): never
    {
        throw ValidationException::withMessages([
            'avatar' => [$message],
        ]);
    }
}
