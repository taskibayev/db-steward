<?php

namespace App\Crud;

use App\ClientDatabase\SchemaTable;

final readonly class RowMutationResult
{
    /**
     * @param array<string, mixed>      $primaryKey
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public SchemaTable $table,
        public array $primaryKey,
        public ?array $before,
        public ?array $after,
        public int $affectedRows,
    ) {
    }
}
