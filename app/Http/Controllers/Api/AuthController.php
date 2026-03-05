<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public const MOBILE_API_ABILITY = 'mobile:access';
    private const ACCOUNT_ATTEMPT_DECAY_SECONDS = 300;
    private const ACCOUNT_ATTEMPT_LIMIT = 10;
    private const LOGIN_ATTEMPT_LIMIT = 5;
    private const LOGIN_ATTEMPT_DECAY_SECONDS = 60;
    private const MAX_DEVICE_NAME_LENGTH = 120;
    private const MOBILE_API_TOKEN_TTL_DAYS = 30;

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:'.self::MAX_DEVICE_NAME_LENGTH],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $password = (string) $validated['password'];
        $throttleKeys = $this->loginThrottleKeys($email, $request);

        if ($response = $this->ensureIsNotRateLimited($throttleKeys, $request)) {
            return $response;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            $this->hitLoginRateLimiter($throttleKeys);

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 422);
        }

        if (! $user->isActiveAccount()) {
            $this->hitLoginRateLimiter($throttleKeys);

            return response()->json([
                'message' => 'Akun ini sedang diblokir. Hubungi super admin.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $this->hitLoginRateLimiter($throttleKeys);

            return response()->json([
                'message' => 'Email belum terverifikasi. Silakan verifikasi email terlebih dahulu.',
            ], 403);
        }

        if ($user->isAdmin()) {
            $this->hitLoginRateLimiter($throttleKeys);

            return response()->json([
                'message' => 'Akses API mobile hanya untuk akun pengguna biasa.',
            ], 403);
        }

        $this->clearLoginRateLimiter($throttleKeys);

        $deviceName = $this->resolveDeviceName($request, $validated['device_name'] ?? null);

        $token = $user->createToken(
            $deviceName,
            [self::MOBILE_API_ABILITY],
            now()->addDays(self::MOBILE_API_TOKEN_TTL_DAYS)
        )->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()?->delete();

        return response()->json([
            'message' => 'Semua sesi berhasil diakhiri.',
        ]);
    }

    private function ensureIsNotRateLimited(array $throttleKeys, Request $request): ?JsonResponse
    {
        foreach ($throttleKeys as $key => $config) {
            if (! RateLimiter::tooManyAttempts($key, $config['limit'])) {
                continue;
            }

            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ], 429);
        }

        return null;
    }

    private function hitLoginRateLimiter(array $throttleKeys): void
    {
        foreach ($throttleKeys as $key => $config) {
            RateLimiter::hit($key, $config['decay']);
        }
    }

    private function clearLoginRateLimiter(array $throttleKeys): void
    {
        foreach (array_keys($throttleKeys) as $key) {
            RateLimiter::clear($key);
        }
    }

    private function loginThrottleKeys(string $email, Request $request): array
    {
        $normalizedEmail = Str::transliterate(Str::lower($email));

        return [
            $normalizedEmail.'|'.$request->ip() => [
                'limit' => self::LOGIN_ATTEMPT_LIMIT,
                'decay' => self::LOGIN_ATTEMPT_DECAY_SECONDS,
            ],
            $normalizedEmail => [
                'limit' => self::ACCOUNT_ATTEMPT_LIMIT,
                'decay' => self::ACCOUNT_ATTEMPT_DECAY_SECONDS,
            ],
        ];
    }

    private function resolveDeviceName(Request $request, mixed $validatedDeviceName): string
    {
        $deviceName = trim((string) ($validatedDeviceName ?? ''));

        if ($deviceName === '') {
            $deviceName = trim((string) ($request->userAgent() ?? ''));
        }

        if ($deviceName === '') {
            $deviceName = 'mobile-app';
        }

        return Str::limit($deviceName, self::MAX_DEVICE_NAME_LENGTH, '');
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? ''),
            'email' => (string) $user->email,
            'role' => (string) $user->roleKeyFromAdminLevel(),
            'role_label' => (string) $user->roleLabel(),
            'admin_level' => (int) $user->adminLevel(),
            'account_status' => (string) ($user->account_status ?? 'active'),
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
            'affiliation' => [
                'type' => $user->affiliation_type,
                'name' => $user->affiliation_name,
                'student_id_type' => $user->student_id_type,
                'student_id_number' => $user->student_id_number,
                'status' => $user->affiliation_status,
            ],
        ];
    }
}
