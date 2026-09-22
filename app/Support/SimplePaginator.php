<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Slices an already-fetched Collection into a page — used for the KPI detail table,
 * where the full (uncapped) dataset is already fetched once for export purposes,
 * so paginating in PHP avoids a second, differently-shaped query just for the screen.
 */
class SimplePaginator
{
    /**
     * @return array{data: Collection, total: int, page: int, perPage: int, lastPage: int}
     */
    public static function paginate(Collection $items, int $page, int $perPage): array
    {
        $total    = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));
        $data     = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return compact('data', 'total', 'page', 'perPage', 'lastPage');
    }
}
