<?php

namespace App\Sql;

final readonly class ParsedSql
{
    /**
     * @param list<string> $writeTables
     * @param list<string> $readTables
     */
    public function __construct(
        public string $sql,
        public SqlOperation $operation,
        public array $writeTables,
        public array $readTables,
    ) {
    }

    /** @return list<string> */
    public function allTables(): array
    {
        $tables = array_values(array_unique([...$this->writeTables, ...$this->readTables]));
        sort($tables);

        return $tables;
    }
}
