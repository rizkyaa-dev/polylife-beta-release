<?php

namespace Tests\Support;

use PDO;
use PDOException;

/** Test-only PHP <8.4 support: SQL BEGIN is not tracked by legacy PDO SQLite. */
final class ScienceE2eSqlitePdo extends PDO
{
    private bool $immediate = false;

    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            throw new PDOException('There is already an active transaction');
        }
        $this->exec('BEGIN IMMEDIATE TRANSACTION');
        $this->immediate = true;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->immediate || parent::inTransaction();
    }

    public function commit(): bool
    {
        if (! $this->immediate) {
            return parent::commit();
        }
        $this->exec('COMMIT');
        $this->immediate = false;

        return true;
    }

    public function rollBack(): bool
    {
        if (! $this->immediate) {
            return parent::rollBack();
        }
        $this->exec('ROLLBACK');
        $this->immediate = false;

        return true;
    }
}
