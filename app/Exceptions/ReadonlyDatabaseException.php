<?php

namespace App\Exceptions;

use RuntimeException;

class ReadonlyDatabaseException extends RuntimeException
{
    public function __construct(string $operation = 'write')
    {
        parent::__construct(
            "Operation '{$operation}' is not allowed on the HOSxP read-only connection. " .
            "Use DB::connection('pgsql') for write operations."
        );
    }
}
