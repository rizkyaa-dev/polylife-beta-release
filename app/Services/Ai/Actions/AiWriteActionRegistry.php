<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Model;

class AiWriteActionRegistry
{
    /** @var array<string, AiWriteAction> */
    private array $actions;

    public function __construct(
        CreateJadwalAction $createJadwal,
        CreateKeuanganAction $createKeuangan,
        CreateTodolistAction $createTodolist,
        CreateCatatanAction $createCatatan
    ) {
        $this->actions = [];

        foreach ([$createJadwal, $createKeuangan, $createTodolist, $createCatatan] as $action) {
            $this->actions[$action->toolName()] = $action;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validatePayload(string $toolName, array $payload): array
    {
        return $this->actionFor($toolName)->validatePayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(string $toolName, User $user, array $payload): Model
    {
        return $this->actionFor($toolName)->execute($user, $payload);
    }

    private function actionFor(string $toolName): AiWriteAction
    {
        $action = $this->actions[$toolName] ?? null;

        if (! $action) {
            throw new AiActionException("Tool '{$toolName}' tidak mendukung eksekusi otomatis.");
        }

        return $action;
    }
}
