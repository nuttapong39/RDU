<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RduIndicator extends Model
{
    protected $fillable = [
        'code', 'name', 'category', 'description',
        'numerator_desc', 'denominator_desc',
        'target_value', 'target_direction', 'unit',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'target_value' => 'float',
        'is_active'    => 'boolean',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(RduIndicatorResult::class, 'indicator_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /** Whether this KPI's HOSxP query is implemented (config rdu.hosxp_kpis); the rest are still query stubs. */
    public function hasHosxpQuery(): bool
    {
        return in_array($this->code, config('rdu.hosxp_kpis', []), true);
    }

    public function targetLabel(): string
    {
        $op = $this->target_direction === 'higher' ? '≥' : '≤';
        return "{$op} {$this->target_value}%";
    }
}
