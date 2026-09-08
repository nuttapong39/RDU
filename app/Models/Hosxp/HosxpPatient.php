<?php

namespace App\Models\Hosxp;

class HosxpPatient extends ReadonlyHosxpModel
{
    protected $table = 'patient';

    protected $primaryKey = 'hn';

    public $incrementing = false;

    protected $keyType = 'string';
}
