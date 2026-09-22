<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RduDoctorAlias extends Model
{
    protected $fillable = ['doctor_code'];

    /** Stable display code, e.g. "DR-01" — derived from the row's own id, never stored redundantly. */
    public function code(): string
    {
        return 'DR-' . str_pad((string) $this->id, 2, '0', STR_PAD_LEFT);
    }
}
