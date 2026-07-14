<?php

namespace App\Database;

use Illuminate\Database\SQLiteConnection;

class ImmediateSQLiteConnection extends SQLiteConnection
{
    protected function executeBeginTransactionStatement(): void
    {
        $this->getPdo()->exec('BEGIN IMMEDIATE TRANSACTION');
    }
}
