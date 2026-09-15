<?php

namespace App\Sql;

final readonly class SqlQueryResult
{
    /**
     * @param list<string>               $columns
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public bool $truncated,
        public ?int $affectedRows,
    ) {
    }
}
