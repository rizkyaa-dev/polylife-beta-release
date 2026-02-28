<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $password = (string) $validated['password'];

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 422);
        }

        if (! $user->isActiveAccount()) {
            return response()->json([
                'message' => 'Akun ini sedang diblokir. Hubungi super admin.',
            ], 403);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Akses API mobile hanya untuk akun pengguna biasa.',
            ], 403);
        }

        $deviceName = trim((string) ($validated['device_name'] ?? ''));
        if ($deviceName === '') {
            $deviceName = trim((string) ($request->userAgent() ?? 'mobile-app'));
        }
        if ($deviceName === '') {
            $deviceName = 'mobile-app';
        }

        $token = $user->createToken($deviceName)->plainTextToken;

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
