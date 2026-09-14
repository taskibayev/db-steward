<?php

namespace App\ClientDatabase;

final readonly class RowPage
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(
        public SchemaTable $table,
        public array $rows,
        public int $page,
        public int $pageSize,
        public int $total,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'table' => $this->table->toArray(),
            'rows' => $this->rows,
            'pagination' => [
                'page' => $this->page,
                'pageSize' => $this->pageSize,
                'total' => $this->total,
                'pages' => max(1, (int) ceil($this->total / $this->pageSize)),
            ],
        ];
    }
}
