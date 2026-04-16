<?php

namespace App\Actions\Matkul;

use App\Models\Matkul;

class UpdateMatkulAction
{
    public function __construct(
        private readonly PrepareMatkulPayloadAction $prepareMatkulPayloadAction
    ) {
    }

    public function __invoke(Matkul $matkul, array $data): Matkul
    {
        $matkul->update(($this->prepareMatkulPayloadAction)($data));

        return $matkul;
    }
}
