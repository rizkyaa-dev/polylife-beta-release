<?php

namespace App\Services;

use App\Models\AffiliationBroadcast;
use App\Models\AffiliationBroadcastPushLog;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class AffiliationBroadcastPushService
{
    public function dispatchForBroadcast(AffiliationBroadcast $broadcast): array
    {
        if (! $broadcast->send_push || ! $this->canSend()) {
            return ['success' => 0, 'failed' => 0];
        }

        $broadcast->loadMissing('targets');
        $broadcast->push_started_at = now();
        $broadcast->push_completed_at = null;
        $broadcast->push_success_count = 0;
        $broadcast->push_failed_count = 0;
        $broadcast->save();

        $query = $this->recipientQuery($broadcast);
        $webPush = $this->buildWebPush();

        $successCount = 0;
        $failedCount = 0;

        $query->chunkById(100, function ($users) use ($broadcast, $webPush, &$successCount, &$failedCount): void {
            $payload = $this->buildPayload($broadcast);
            $endpointUserMap = [];
            $logs = [];

            /** @var User $user */
            foreach ($users as $user) {
                foreach ($user->pushSubscriptions as $subscription) {
                    try {
                        $webPush->queueNotification(
                            WebPushSubscription::create([
                                'endpoint' => $subscription->endpoint,
                                'publicKey' => $subscription->p256dh,
                                'authToken' => $subscription->auth_token,
                                'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                            ]),
                            $payload
                        );
                        $endpointUserMap[$subscription->endpoint] = $user->id;
                    } catch (\Throwable $e) {
                        $failedCount++;
                        $logs[] = [
                            'broadcast_id' => $broadcast->id,
                            'user_id' => $user->id,
                            'endpoint' => $subscription->endpoint,
                            'status' => 'failed',
                            'error_message' => mb_substr($e->getMessage(), 0, 1000),
                            'sent_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            try {
                foreach ($webPush->flush() as $report) {
                    $endpoint = (string) $report->getEndpoint();
                    $userId = $endpointUserMap[$endpoint] ?? null;
                    $isSuccess = $report->isSuccess();
                    $status = $isSuccess ? 'sent' : ($report->isSubscriptionExpired() ? 'expired' : 'failed');

                    if ($isSuccess) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }

                    $logs[] = [
                        'broadcast_id' => $broadcast->id,
                        'user_id' => $userId,
                        'endpoint' => $endpoint,
                        'status' => $status,
                        'error_message' => $isSuccess ? null : mb_substr((string) $report->getReason(), 0, 1000),
                        'sent_at' => $isSuccess ? now() : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $endpoint)->delete();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('affiliation_broadcast.push_flush_failed', [
                    'broadcast_id' => $broadcast->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if (! empty($logs)) {
                foreach (array_chunk($logs, 200) as $logChunk) {
                    AffiliationBroadcastPushLog::query()->insert($logChunk);
                }
            }
        });

        $broadcast->push_completed_at = now();
        $broadcast->push_success_count = $successCount;
        $broadcast->push_failed_count = $failedCount;
        $broadcast->save();

        return [
            'success' => $successCount,
            'failed' => $failedCount,
        ];
    }

    protected function recipientQuery(AffiliationBroadcast $broadcast)
    {
        $query = User::query()
            ->where('account_status', 'active')
            ->with('pushSubscriptions')
            ->whereHas('pushSubscriptions');

        if ($broadcast->target_mode !== AffiliationBroadcast::TARGET_MODE_GLOBAL) {
            $targets = $broadcast->targets
                ->filter(fn ($target) => filled($target->affiliation_name))
                ->values();

            if ($targets->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(function ($outerQuery) use ($targets): void {
                foreach ($targets as $target) {
                    $outerQuery->orWhere(function ($matchQuery) use ($target): void {
                        $matchQuery->where('affiliation_name', $target->affiliation_name);

                        if (filled($target->affiliation_type)) {
                            $matchQuery->where(function ($typeQuery) use ($target): void {
                                $typeQuery->whereNull('affiliation_type')
                                    ->orWhere('affiliation_type', $target->affiliation_type);
                            });
                        }
                    });
                }
            });
        }

        return $query->orderBy('id');
    }

    protected function canSend(): bool
    {
        $hasKeys = config('services.webpush.public_key') && config('services.webpush.private_key');

        return $hasKeys && class_exists(WebPush::class);
    }

    protected function buildWebPush(): WebPush
    {
        $options = [
            'VAPID' => [
                'subject' => config('services.webpush.subject') ?: config('app.url'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ];

        $webPush = new WebPush($options);
        $webPush->setAutomaticPadding(0);

        return $webPush;
    }

    protected function buildPayload(AffiliationBroadcast $broadcast): string
    {
        $body = trim((string) $broadcast->body);
        if (mb_strlen($body) > 180) {
            $body = mb_substr($body, 0, 177).'...';
        }

        $url = Route::has('pengumuman.show')
            ? route('pengumuman.show', $broadcast->id)
            : route('workspace.home');

        $imageUrl = $broadcast->image_url;
        if ($imageUrl && ! str_starts_with($imageUrl, 'http://') && ! str_starts_with($imageUrl, 'https://')) {
            $imageUrl = url($imageUrl);
        }

        return json_encode([
            'title' => $broadcast->title,
            'body' => $body,
            'url' => $url,
            'tag' => 'affiliation-broadcast-'.$broadcast->id,
            'image' => $imageUrl,
            'data' => [
                'url' => $url,
                'broadcast_id' => $broadcast->id,
            ],
        ]);
    }
}
