<?php

namespace App\Actions\Catatan;

use App\Models\Catatan;

class DeleteCatatanAction
{
    public function __invoke(Catatan $catatan): void
    {
        $catatan->forceDelete();
    }
}
