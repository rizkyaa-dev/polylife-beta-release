<?php

use App\Actions\PushSubscription\DeletePushSubscriptionAction;
use App\Actions\PushSubscription\StorePushSubscriptionAction;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('delete push subscription action only deletes subscriptions owned by the user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $subscription = PushSubscription::query()->create([
        'user_id' => $owner->id,
        'endpoint' => 'https://example.test/push/abc',
        'p256dh' => 'key-a',
        'auth_token' => 'auth-a',
        'content_encoding' => 'aes128gcm',
        'user_agent' => 'Pest',
    ]);

    app(DeletePushSubscriptionAction::class)($otherUser->id, $subscription->endpoint);

    expect(PushSubscription::query()->whereKey($subscription->id)->exists())->toBeTrue();
});

test('store push subscription action rejects taking over another users endpoint with different keys', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    PushSubscription::query()->create([
        'user_id' => $owner->id,
        'endpoint' => 'https://example.test/push/xyz',
        'p256dh' => 'key-a',
        'auth_token' => 'auth-a',
        'content_encoding' => 'aes128gcm',
        'user_agent' => 'Pest',
    ]);

    expect(fn () => app(StorePushSubscriptionAction::class)(
        $otherUser,
        [
            'endpoint' => 'https://example.test/push/xyz',
            'keys' => [
                'p256dh' => 'key-b',
                'auth' => 'auth-b',
            ],
            'content_encoding' => 'aes128gcm',
        ],
        'Pest'
    ))->toThrow(ValidationException::class);
});
