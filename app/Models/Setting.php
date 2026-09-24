<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value', 'type', 'description'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function putValue(string $key, ?string $value, ?string $description = null): void
    {
        static::updateOrCreate(['key' => $key], array_filter([
            'value'       => $value,
            'type'        => 'string',
            'description' => $description,
        ], fn ($v) => $v !== null));
    }

    public static function forgetKeys(array $keys): void
    {
        static::query()->whereIn('key', $keys)->delete();
    }
}
