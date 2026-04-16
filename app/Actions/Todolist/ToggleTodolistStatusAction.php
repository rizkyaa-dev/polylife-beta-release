<?php

namespace App\Actions\Todolist;

use App\Models\Todolist;

class ToggleTodolistStatusAction
{
    public function __invoke(Todolist $todolist, bool $status): Todolist
    {
        $todolist->update([
            'status' => $status,
        ]);

        $todolist->reminders()->update([
            'aktif' => $status ? false : true,
        ]);

        return $todolist->fresh(['reminders']);
    }
}
