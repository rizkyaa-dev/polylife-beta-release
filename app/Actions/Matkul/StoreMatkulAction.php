<?php

namespace App\Actions\Matkul;

use App\Models\Matkul;

class StoreMatkulAction
{
    public function __construct(
        private readonly PrepareMatkulPayloadAction $prepareMatkulPayloadAction
    ) {
    }

    public function __invoke(int $userId, array $data): Matkul
    {
        $data['user_id'] = $userId;
        $data = ($this->prepareMatkulPayloadAction)($data);

        return Matkul::query()->create($data);
    }
}
