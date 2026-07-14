<?php

namespace App\Providers;

use App\Database\ImmediateSQLiteConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('sqlite', fn ($connection, $database, $prefix, $config) =>
            new ImmediateSQLiteConnection($connection, $database, $prefix, $config)
        );
    }
}
