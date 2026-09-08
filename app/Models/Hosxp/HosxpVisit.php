<?php

namespace App\Models\Hosxp;

class HosxpVisit extends ReadonlyHosxpModel
{
    protected $table = 'ovst';

    protected $primaryKey = 'vn';

    public $incrementing = false;

    protected $keyType = 'string';
}
