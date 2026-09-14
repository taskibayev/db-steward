<?php

namespace App\ClientDatabase;

final readonly class SchemaTable
{
    /**
     * @param list<SchemaColumn> $columns
     * @param list<string>       $primaryKey
     */
    public function __construct(
        public string $name,
        public string $kind,
        public array $columns,
        public array $primaryKey,
    ) {
    }

    public function isReadOnly(): bool
    {
        return 'view' === $this->kind || [] === $this->primaryKey;
    }

    public function hasColumn(string $name): bool
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<array<string, bool|string>>|array<string>|bool|string> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'kind' => $this->kind,
            'columns' => array_map(static fn (SchemaColumn $column): array => $column->toArray(), $this->columns),
            'primaryKey' => $this->primaryKey,
            'readOnly' => $this->isReadOnly(),
        ];
    }
}
