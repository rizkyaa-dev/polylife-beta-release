<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\ReminderPushLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class ReminderPushService
{
    /**
    * Seconds before due time when push should fire.
    */
    private const MILESTONES = [86400, 3600, 300, 60, 0];

    public function sendDueReminderPushes(?Carbon $now = null): int
    {
        if (! $this->canSend()) {
            return 0;
        }

        $now = $now ?: Carbon::now();
        $reminders = $this->dueReminders($now);
        $webPush = $this->buildWebPush();

        $totalSent = 0;

        foreach ($reminders as $reminder) {
            $secondsLeft = $now->diffInSeconds($reminder->waktu_reminder, false);
            foreach (self::MILESTONES as $milestone) {
                if ($secondsLeft > $milestone) {
                    continue;
                }

                if ($this->alreadySent($reminder->id, $reminder->user_id, $milestone)) {
                    continue;
                }

                $sentForReminder = $this->queueReminderNotifications($reminder, $milestone, $webPush);

                if ($sentForReminder > 0) {
                    $this->markSent($reminder->id, $reminder->user_id, $milestone, $now);
                    $totalSent += $sentForReminder;
                }
            }
        }

        if ($totalSent > 0) {
            try {
                foreach ($webPush->flush() as $report) {
                    if ($report->isSubscriptionExpired()) {
                        $this->dropExpiredSubscription($report->getEndpoint());
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('reminder.push.flush_failed', ['error' => $e->getMessage()]);
            }
        }

        return $totalSent;
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

    protected function dueReminders(Carbon $now): Collection
    {
        return Reminder::with(['user.pushSubscriptions', 'todolist', 'tugas', 'jadwal', 'kegiatan'])
            ->where('aktif', true)
            ->whereBetween('waktu_reminder', [$now->copy()->subDay(), $now->copy()->addDay()])
            ->get();
    }

    protected function resolveTitle(Reminder $reminder): string
    {
        return optional($reminder->todolist)->nama_item
            ?? optional($reminder->tugas)->nama_tugas
            ?? optional($reminder->kegiatan)->nama_kegiatan
            ?? optional($reminder->jadwal)->catatan_tambahan
            ?? 'Reminder';
    }

    protected function queueReminderNotifications(Reminder $reminder, int $milestoneSeconds, WebPush $webPush): int
    {
        $user = $reminder->user;
        if (! $user) {
            return 0;
        }

        $subscriptions = $user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $title = $this->resolveTitle($reminder);
        $deadline = $reminder->waktu_reminder?->copy()->toIso8601String() ?? '';
        $url = route('workspace.home');
        $tag = sprintf('reminder-%s-%s', $reminder->id, $milestoneSeconds);

        $body = match (true) {
            $milestoneSeconds <= 0 => 'Waktu habis. Segera selesaikan atau jadwalkan ulang.',
            $milestoneSeconds <= 60 => 'Tinggal 1 menit lagi.',
            $milestoneSeconds <= 300 => 'Tinggal 5 menit lagi.',
            $milestoneSeconds <= 3600 => 'Tinggal 1 jam lagi.',
            default => 'Reminder akan segera jatuh tempo.',
        };

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag,
            'data' => [
                'url' => $url,
                'deadline' => $deadline,
                'reminder_id' => $reminder->id,
                'milestone' => $milestoneSeconds,
            ],
        ]);

        $queued = 0;

        /** @var PushSubscription $subscription */
        foreach ($subscriptions as $subscription) {
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
                $queued++;
            } catch (\Throwable $e) {
                Log::warning('reminder.push.queue_failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    protected function alreadySent(int $reminderId, int $userId, int $milestoneSeconds): bool
    {
        return ReminderPushLog::where([
            'reminder_id' => $reminderId,
            'user_id' => $userId,
            'milestone_seconds' => $milestoneSeconds,
        ])->exists();
    }

    protected function markSent(int $reminderId, int $userId, int $milestoneSeconds, Carbon $timestamp): void
    {
        ReminderPushLog::create([
            'reminder_id' => $reminderId,
            'user_id' => $userId,
            'milestone_seconds' => $milestoneSeconds,
            'sent_at' => $timestamp,
        ]);
    }

    protected function dropExpiredSubscription(string $endpoint): void
    {
        PushSubscription::where('endpoint', $endpoint)->delete();
    }
}
