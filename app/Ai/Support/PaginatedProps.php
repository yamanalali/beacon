<?php

namespace App\Ai\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Laravel-resource style {data, links, meta} for DataTable server mode. */
class PaginatedProps
{
    /** @return array{data:list<mixed>,links:array<string,?string>,meta:array<string,mixed>} */
    public static function from(LengthAwarePaginator $p): array
    {
        $a = $p->toArray();

        return [
            'data' => array_values($a['data']),
            'links' => ['first' => $a['first_page_url'], 'last' => $a['last_page_url'], 'prev' => $a['prev_page_url'], 'next' => $a['next_page_url']],
            'meta' => [
                'current_page' => $a['current_page'], 'from' => $a['from'], 'last_page' => $a['last_page'],
                'links' => $a['links'], 'path' => $a['path'], 'per_page' => $a['per_page'], 'to' => $a['to'], 'total' => $a['total'],
            ],
        ];
    }
}
