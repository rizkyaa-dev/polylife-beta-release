<?php

namespace App\Actions\Catatan;

use App\Models\Catatan;
use Illuminate\Support\Collection;

class BulkTrashCatatanAction
{
    /**
     * @param  Collection<int, Catatan>  $catatans
     */
    public function __invoke(Collection $catatans): int
    {
        $ids = $catatans
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            return 0;
        }

        return Catatan::query()
            ->whereIn('id', $ids)
            ->update(['status_sampah' => true]);
    }
}
