<?php

namespace App\Actions\Tugas;

use App\Models\Tugas;

class FindOwnedTugasAction
{
    public function __invoke(int $userId, mixed $id): Tugas
    {
        return Tugas::query()
            ->where('user_id', $userId)
            ->findOrFail($id);
    }
}
