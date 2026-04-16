<?php

namespace App\Actions\Catatan;

use Illuminate\Support\Collection;

class BulkForceDeleteCatatanAction
{
    public function __construct(
        private readonly DeleteCatatanAction $deleteCatatanAction
    ) {
    }

    /**
     * @param  Collection<int, \App\Models\Catatan>  $catatans
     */
    public function __invoke(Collection $catatans): int
    {
        $affected = 0;

        foreach ($catatans as $catatan) {
            ($this->deleteCatatanAction)($catatan);
            $affected++;
        }

        return $affected;
    }
}
