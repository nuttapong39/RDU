<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugCodeSetEditor extends Model
{
    protected $fillable = ['user_id', 'granted_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
