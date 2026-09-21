<?php

namespace Tests\Unit\Ai;

use Illuminate\Database\SQLiteConnection;
use Tests\Support\ScienceE2eSqlitePdo;
use Tests\TestCase;

class ScienceE2eSqliteConnectionTest extends TestCase
{
    public function test_immediate_transactions_preserve_pdo_commit_rollback_and_savepoints(): void
    {
        $connection = new SQLiteConnection(new ScienceE2eSqlitePdo('sqlite::memory:'), ':memory:');
        $connection->statement('CREATE TABLE evidence (value INTEGER)');
        $connection->transaction(function () use ($connection): void {
            $this->assertTrue($connection->getPdo()->inTransaction());
            $connection->insert('INSERT INTO evidence VALUES (1)');
            $connection->beginTransaction();
            $connection->insert('INSERT INTO evidence VALUES (2)');
            $connection->rollBack();
        });
        $this->assertFalse($connection->getPdo()->inTransaction());
        $this->assertSame(1, $connection->scalar('SELECT SUM(value) FROM evidence'));
        $connection->beginTransaction();
        $connection->insert('INSERT INTO evidence VALUES (3)');
        $connection->rollBack();
        $this->assertSame(1, $connection->scalar('SELECT SUM(value) FROM evidence'));
    }
}
