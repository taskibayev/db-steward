<?php

namespace App\ClientDatabase;

final readonly class SchemaColumn
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public bool $autoincrement,
        public bool $generated,
        public bool $hasDefault,
    ) {
    }

    /** @return array<string, bool|string> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'autoincrement' => $this->autoincrement,
            'generated' => $this->generated,
            'hasDefault' => $this->hasDefault,
        ];
    }
}
