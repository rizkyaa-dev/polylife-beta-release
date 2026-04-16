<?php

namespace App\Actions\Catatan;

use Illuminate\Support\Collection;

class BulkRestoreCatatanAction
{
    public function __construct(
        private readonly RestoreCatatanAction $restoreCatatanAction
    ) {
    }

    /**
     * @param  Collection<int, \App\Models\Catatan>  $catatans
     */
    public function __invoke(Collection $catatans): int
    {
        $affected = 0;

        foreach ($catatans as $catatan) {
            ($this->restoreCatatanAction)($catatan);
            $affected++;
        }

        return $affected;
    }
}
