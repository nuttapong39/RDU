<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RduIndicatorResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'indicator_id', 'year', 'month',
        'numerator', 'denominator', 'result',
        'status', 'calculated_at', 'calculated_by',
    ];

    protected $casts = [
        'result'         => 'float',
        'calculated_at'  => 'datetime',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RduIndicator::class, 'indicator_id');
    }
}
