<?php

namespace Tests\Unit;

use App\Database\ImmediateSQLiteConnection;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

class ImmediateSQLiteConnectionTest extends TestCase
{
    public function test_sqlite_transactions_reserve_the_writer_lock_immediately(): void
    {
        $this->assertInstanceOf(ImmediateSQLiteConnection::class, DB::connection());

        $path = tempnam(sys_get_temp_dir(), 'xboard-sqlite-');
        $firstPdo = new PDO('sqlite:' . $path);
        $secondPdo = new PDO('sqlite:' . $path);
        $secondPdo->exec('PRAGMA busy_timeout = 0');
        $first = new ImmediateSQLiteConnection($firstPdo, $path);
        $second = new ImmediateSQLiteConnection($secondPdo, $path);

        try {
            $first->beginTransaction();

            try {
                $second->beginTransaction();
                $this->fail('A concurrent SQLite writer must wait before reading mutable order state.');
            } catch (PDOException $exception) {
                $this->assertStringContainsString('database is locked', $exception->getMessage());
            }
        } finally {
            if ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
            @unlink($path);
        }
    }
}
