<?php

namespace App\Models\Hosxp;

use App\Exceptions\ReadonlyDatabaseException;
use Illuminate\Database\Eloquent\Model;

abstract class ReadonlyHosxpModel extends Model
{
    protected $connection = 'rdu';

    public $timestamps = false;

    // Block all write operations

    public function save(array $options = []): bool
    {
        throw new ReadonlyDatabaseException('save');
    }

    public function delete(): bool|null
    {
        throw new ReadonlyDatabaseException('delete');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new ReadonlyDatabaseException('update');
    }

    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        throw new ReadonlyDatabaseException('insert');
    }

    protected function performUpdate(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        throw new ReadonlyDatabaseException('update');
    }

    public static function create(array $attributes = []): static
    {
        throw new ReadonlyDatabaseException('create');
    }

    public static function forceCreate(array $attributes): static
    {
        throw new ReadonlyDatabaseException('forceCreate');
    }
}
