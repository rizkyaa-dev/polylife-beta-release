<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\SecurePasswordUpdateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\AffiliationRequest;
use App\Models\User;
use App\Services\ProfileAvatarService;
use App\Support\Api\UserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

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

    public function updatePassword(Request $request, SecurePasswordUpdateAction $action): JsonResponse
    {
        $user = $request->user();
        $this->ensurePasswordUpdateIsNotRateLimited($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            RateLimiter::hit($this->passwordUpdateThrottleKey($request), 60);

            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak cocok.'],
            ]);
        }

        $action($user, (string) $validated['current_password'], (string) $validated['password']);
        RateLimiter::clear($this->passwordUpdateThrottleKey($request));

        return response()->json([
            'message' => 'Password berhasil diperbarui. Silakan login kembali.',
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password konfirmasi tidak cocok.'],
            ]);
        }

        DB::transaction(function () use ($user): void {
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            $user->delete();
        });

        return response()->json([
            'message' => 'Akun berhasil dihapus.',
        ]);
    }

    public function submitAffiliationRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'affiliation_type' => ['required', Rule::in(array_keys($this->affiliationTypeOptions()))],
            'affiliation_name' => ['required', 'string', 'max:160'],
            'student_id_type' => ['required', Rule::in(array_keys($this->identityTypeOptions()))],
            'student_id_number' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->pendingAffiliationRequest()->exists()) {
                throw ValidationException::withMessages([
                    'affiliation_name' => ['Masih ada pengajuan afiliasi yang menunggu review.'],
                ]);
            }

            AffiliationRequest::query()->create([
                'user_id' => $lockedUser->id,
                'affiliation_type' => $validated['affiliation_type'],
                'affiliation_name' => $this->normalize((string) $validated['affiliation_name']),
                'student_id_type' => $this->nullableString($validated['student_id_type'] ?? null),
                'student_id_number' => $this->normalize((string) $validated['student_id_number']),
                'status' => AffiliationRequest::STATUS_PENDING,
            ]);
        });

        return response()->json([
            'message' => 'Pengajuan afiliasi berhasil dikirim.',
            'data' => [
                'user' => UserPayload::fromUser($user->fresh()),
            ],
        ], 201);
    }

    public function cancelAffiliationRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        $pendingRequest = $user->pendingAffiliationRequest;

        if (! $pendingRequest) {
            return response()->json([
                'message' => 'Tidak ada pengajuan afiliasi yang menunggu review.',
                'data' => [
                    'user' => UserPayload::fromUser($user->fresh()),
                ],
            ]);
        }

        $pendingRequest->forceFill([
            'status' => AffiliationRequest::STATUS_CANCELED,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Pengajuan afiliasi dibatalkan.',
            'data' => [
                'user' => UserPayload::fromUser($user->fresh()),
            ],
        ]);
    }

    public function uploadAvatar(Request $request, ProfileAvatarService $avatarService): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:512'],
        ]);

        $user = $request->user();
        $avatarService->store($user, $validated['avatar']);

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => [
                'user' => UserPayload::fromUser($user->fresh()),
            ],
        ]);
    }

    public function deleteAvatar(Request $request, ProfileAvatarService $avatarService): JsonResponse
    {
        $user = $request->user();
        $avatarService->delete($user);

        return response()->json([
            'message' => 'Foto profil berhasil dihapus.',
            'data' => [
                'user' => UserPayload::fromUser($user->fresh()),
            ],
        ]);
    }

    private function ensurePasswordUpdateIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->passwordUpdateThrottleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->passwordUpdateThrottleKey($request));

        throw ValidationException::withMessages([
            'current_password' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }

    private function passwordUpdateThrottleKey(Request $request): string
    {
        $user = $request->user();
        $identity = $user ? $user->getAuthIdentifier().'|'.$user->email : $request->ip();

        return Str::transliterate('api-profile-password-update|'.$identity.'|'.$request->ip());
    }

    /**
     * @return array<string, string>
     */
    private function affiliationTypeOptions(): array
    {
        return [
            'school' => 'Sekolah',
            'university' => 'Universitas',
            'institute' => 'Institut',
            'polytechnic' => 'Politeknik',
            'academy' => 'Akademi',
            'organization' => 'Organisasi',
            'company' => 'Perusahaan',
            'foundation' => 'Yayasan',
            'other' => 'Lainnya',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function identityTypeOptions(): array
    {
        return [
            'nim' => 'NIM',
            'nrp' => 'NRP',
            'nisn' => 'NISN',
            'nidn' => 'NIDN',
            'nip' => 'NIP',
            'other' => 'Lainnya',
        ];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: trim($value);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
