<?php

namespace App\Actions\Catatan;

use App\Models\Catatan;

class RestoreCatatanAction
{
    public function __invoke(Catatan $catatan): void
    {
        $catatan->update(['status_sampah' => false]);
    }
}
