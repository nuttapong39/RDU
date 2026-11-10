<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugCodeSetItem extends Model
{
    protected $fillable = ['set_key', 'code', 'attribute'];
}
