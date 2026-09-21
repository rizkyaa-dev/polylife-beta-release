<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/** Serializes admission only; inference and numerical execution never hold this lock. */
final class AiRunAdmissionGuard
{
    public function acquire(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('AI admission requires an active database transaction.');
        }
        $query = DB::table('ai_run_admission_locks')->where('name', 'global');
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite has no row locks. Acquire its writer before any snapshot read.
            (clone $query)->update(['name' => 'global']);
        }
        if (! $query->lockForUpdate()->first()) {
            throw new RuntimeException('AI admission lock is missing; apply database migrations before accepting runs.');
        }
    }
}
