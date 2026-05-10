<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Support\Api\UserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);

        $profile->fill([
            'display_name' => $validated['display_name'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'location' => $validated['location'] ?? null,
            'theme_preference' => $validated['theme_preference'],
            'timezone' => $validated['timezone'] ?? null,
            'locale' => $validated['locale'] ?? null,
            'preferences' => $profile->preferences ?? [],
        ]);
        $profile->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'user' => UserPayload::fromUser($user->fresh()),
            ],
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:512'],
        ]);

        $user = $request->user();
        $file = $validated['avatar'];
        $binary = file_get_contents($file->getRealPath());

        if ($binary === false || $binary === '') {
            return response()->json([
                'message' => 'Foto profil tidak bisa dibaca.',
                'errors' => ['avatar' => ['Foto profil tidak bisa dibaca.']],
            ], 422);
        }

        [$width, $height] = getimagesizefromstring($binary) ?: [0, 0];
        if ($width < 64 || $height < 64 || $width > 256 || $height > 256) {
            return response()->json([
                'message' => 'Foto profil harus berukuran 64 sampai 256 px.',
                'errors' => ['avatar' => ['Foto profil harus berukuran 64 sampai 256 px.']],
            ], 422);
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
                'image' => $binary,
                'mime_type' => $file->getMimeType() ?: 'image/webp',
                'width' => $width,
                'height' => $height,
                'size' => strlen($binary),
            ]
        );

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => [
                'user' => UserPayload::fromUser($user->fresh()),
            ],
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        $user->profileAvatar()->delete();

        if ($profile?->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
            $profile->avatar_path = null;
            $profile->save();
        }

        return response()->json([
            'message' => 'Foto profil berhasil dihapus.',
            'data' => [
                'user' => UserPayload::fromUser($user->fresh()),
            ],
        ]);
    }
}
