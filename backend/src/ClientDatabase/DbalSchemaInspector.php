<?php

namespace App\ClientDatabase;

use Doctrine\DBAL\Connection;

final class DbalSchemaInspector
{
    public function table(Connection $connection, string $database, string $name): SchemaTable
    {
        $row = $connection->executeQuery(
            'SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND BINARY TABLE_NAME = BINARY ?',
            [$database, $name],
        )->fetchAssociative();
        if (false === $row) {
            throw new UnknownSchemaIdentifier();
        }
        $columns = $connection->executeQuery(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND BINARY TABLE_NAME = BINARY ? ORDER BY ORDINAL_POSITION',
            [$database, $name],
        )->fetchAllAssociative();
        $primary = $connection->executeQuery(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND BINARY TABLE_NAME = BINARY ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION",
            [$database, $name],
        )->fetchFirstColumn();

        return new SchemaTable(
            (string) $row['TABLE_NAME'],
            'VIEW' === $row['TABLE_TYPE'] ? 'view' : 'table',
            array_map(static function (array $column): SchemaColumn {
                $extra = (string) $column['EXTRA'];

                return new SchemaColumn(
                    (string) $column['COLUMN_NAME'],
                    (string) $column['COLUMN_TYPE'],
                    'YES' === $column['IS_NULLABLE'],
                    str_contains($extra, 'auto_increment'),
                    str_contains($extra, 'GENERATED'),
                    null !== $column['COLUMN_DEFAULT'],
                );
            }, $columns),
            array_map(static fn (mixed $column): string => (string) $column, $primary),
        );
    }
}
