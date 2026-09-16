<?php

namespace App\Services\Ai\Actions;

use App\Actions\Broadcast\MarkPengumumanReadAction;
use App\Models\AffiliationBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class MarkAnnouncementReadAction implements AiWriteAction
{
    public function __construct(private readonly MarkPengumumanReadAction $markRead) {}

    public function toolName(): string
    {
        return 'mark_announcement_read';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'announcement_id' => ['required', 'integer'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $announcement = AffiliationBroadcast::query()
            ->visibleToUser($user)
            ->findOrFail($validated['announcement_id']);
        ($this->markRead)($user, [$announcement->id]);

        return $announcement;
    }
}
