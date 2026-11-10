<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrugCodeSet extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'version', 'changed_at', 'updated_by'];

    protected $casts = ['version' => 'integer', 'changed_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(DrugCodeSetItem::class, 'set_key', 'key');
    }
}
