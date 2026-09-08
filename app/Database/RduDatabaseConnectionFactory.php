<?php

namespace App\Database;

use App\Exceptions\ReadonlyDatabaseException;
use App\Services\DatabaseConfigService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class RduDatabaseConnectionFactory
{
    public function __construct(
        private DatabaseConfigService $configService
    ) {}

    public function make(): Connection
    {
        if ($this->configService->hasConfig()) {
            $config = $this->configService->getConnectionConfig();
            config(['database.connections.rdu' => $config]);
            DB::purge('rdu');
        }

        $connection = DB::connection('rdu');
        ReadonlyConnection::installOn($connection);
        return $connection;
    }

    public function testConnection(): bool
    {
        $connection = $this->make();

        $connection->select('SELECT 1 AS ping');

        try {
            $connection->statement('INSERT INTO _rdu_test_probe VALUES (1)');
            throw new \RuntimeException('Write was not blocked — read-only guard is not active.');
        } catch (ReadonlyDatabaseException) {
            // Expected — guard is working
        }

        return true;
    }
}
