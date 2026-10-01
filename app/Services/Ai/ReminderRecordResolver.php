<?php

namespace App\Services\Ai;

use App\Models\Reminder;
use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Builder;

final class ReminderRecordResolver
{
    public function __construct(
        private readonly ReminderTargetResolver $targets,
        private readonly UserTimeContext $timeContext
    ) {}

    public function resolve(User $user, string $type, string $targetQuery, mixed $currentTime = null): Reminder
    {
        $field = $type.'_id';
        $query = Reminder::query()->where('user_id', $user->id);
        if (filled($currentTime)) {
            $query->where('waktu_reminder', $this->timeContext->parse($user, $currentTime)->format('Y-m-d H:i:s'));
        }

        // Resolve only targets having an eligible owned reminder; a same-name
        // item without a reminder cannot be the requested reminder record.
        $target = $this->targets->resolve($user, $type, $targetQuery, function (Builder $targets) use ($query, $field): void {
            $targets->whereIn($targets->getModel()->getQualifiedKeyName(), (clone $query)->select($field));
        });
        $query->where($field, $target->getKey());

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
