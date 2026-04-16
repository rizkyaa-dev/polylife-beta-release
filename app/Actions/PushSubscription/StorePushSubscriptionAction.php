<?php

namespace App\Actions\PushSubscription;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StorePushSubscriptionAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(User $user, array $validated, ?string $userAgent): PushSubscription
    {
        $existing = PushSubscription::query()
            ->where('endpoint', $validated['endpoint'])
            ->first();

        if ($existing && (int) $existing->user_id !== (int) $user->id) {
            $sameKeys = $existing->p256dh === $validated['keys']['p256dh']
                && $existing->auth_token === $validated['keys']['auth'];

            if (! $sameKeys) {
                throw ValidationException::withMessages([
                    'endpoint' => 'Endpoint subscription sudah dipakai oleh akun lain.',
                ]);
            }
        }

        return PushSubscription::query()->updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $userAgent, 0, 255),
            ]
        );
    }
}
