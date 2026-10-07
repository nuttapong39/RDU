<?php

namespace App\Database;

use App\Exceptions\ReadonlyDatabaseException;

/**
 * Second layer of read-only enforcement on the 'rdu' connection.
 * First layer = ReadonlyHosxpModel (blocks Eloquent writes).
 * This layer = blocks raw DB::statement() write calls.
 * Third layer = PostgreSQL rdu_readonly user (SELECT privilege only).
 */
class ReadonlyConnection
{
    private static array $writeKeywords = [
        'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER',
        'DROP', 'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE',
    ];

    public static function installOn(\Illuminate\Database\Connection $connection): void
    {
        $connection->beforeExecuting(function (string $query, array $bindings) {
            $upper = strtoupper(ltrim($query));
            foreach (self::$writeKeywords as $keyword) {
                if (str_starts_with($upper, $keyword)) {
                    throw new ReadonlyDatabaseException($keyword);
                }
            }
        });
    }
}
