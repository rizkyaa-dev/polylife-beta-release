<?php

namespace App\Actions\NilaiMutu;

use App\Models\NilaiMutu;

class SaveNilaiMutuAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(?NilaiMutu $nilaiMutu, int $userId, array $payload): NilaiMutu
    {
        $payload['user_id'] = $userId;

        if ($nilaiMutu) {
            $nilaiMutu->update($payload);

            return $nilaiMutu->fresh();
        }

        return NilaiMutu::query()->create($payload);
    }
}
