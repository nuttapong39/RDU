<?php

namespace App\Exceptions;

use RuntimeException;

/** A KPI query needed a drug code set the hospital has not configured (ADR 0004). */
class DrugCodeSetNotConfiguredException extends RuntimeException
{
    public function __construct(public readonly string $set)
    {
        parent::__construct("Drug code set '{$set}' is not configured");
    }
}
