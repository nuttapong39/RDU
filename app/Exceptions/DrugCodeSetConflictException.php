<?php

namespace App\Exceptions;

use RuntimeException;

/** Someone else saved the set after this user opened it (the version they hold is stale). */
class DrugCodeSetConflictException extends RuntimeException
{
    public function __construct(public readonly string $setKey, public readonly int $currentVersion)
    {
        parent::__construct("Drug code set {$setKey} was changed by someone else (now version {$currentVersion}).");
    }
}
