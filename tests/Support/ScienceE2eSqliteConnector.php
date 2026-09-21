<?php

namespace Tests\Support;

use Illuminate\Database\Connectors\SQLiteConnector;

final class ScienceE2eSqliteConnector extends SQLiteConnector
{
    protected function createPdoConnection($dsn, $username, #[\SensitiveParameter] $password, $options)
    {
        return new ScienceE2eSqlitePdo($dsn, $username, $password, $options);
    }
}
