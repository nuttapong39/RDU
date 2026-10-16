<?php

namespace App\Queries\Kpi;

/** A KPI query that can explain how it counts (CONTEXT.md: วิธีนับ). Stub queries don't implement it. */
interface DescribesCountingMethod
{
    public function countingMethod(): CountingMethod;
}
