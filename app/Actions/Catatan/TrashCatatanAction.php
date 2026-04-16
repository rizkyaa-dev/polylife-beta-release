<?php

namespace App\Actions\Catatan;

use App\Models\Catatan;

class TrashCatatanAction
{
    public function __invoke(Catatan $catatan): void
    {
        $catatan->update(['status_sampah' => true]);
    }
}
