<?php

namespace App\Services\Ai;

use App\Models\Reminder;
use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;

final class ReminderRecordResolver
{
    public function __construct(
        private readonly ReminderTargetResolver $targets,
        private readonly UserTimeContext $timeContext
    ) {}

    public function resolve(User $user, string $type, string $targetQuery, mixed $currentTime = null): Reminder
    {
        $target = $this->targets->resolve($user, $type, $targetQuery);
        $field = $type.'_id';
        $query = Reminder::query()->where('user_id', $user->id)->where($field, $target->getKey());
        if (filled($currentTime)) {
            $query->where('waktu_reminder', $this->timeContext->parse($user, $currentTime)->format('Y-m-d H:i:s'));
        }

        $matches = $query->orderBy('waktu_reminder')->limit(2)->get();
        if ($matches->isEmpty()) {
            throw new AiActionException('Reminder untuk target tersebut tidak ditemukan.');
        }
        if ($matches->count() > 1) {
            throw new AiActionException('Target memiliki beberapa reminder. Sebutkan waktu reminder lama yang ingin diubah.');
        }

        return $matches->first();
    }
}
