<?php

namespace App\ClientDatabase;

use App\Connection\ClientDatabaseCredentials;
use App\Dto\BrowseRowsQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ClientDatabaseReader::class)]
final class DbalClientDatabaseReader implements ClientDatabaseReader
{
    public function schema(ClientDatabaseCredentials $credentials): array
    {
        $connection = null;
        try {
            $connection = $this->connect($credentials);

            return $this->readSchema($connection, $credentials->database);
        } catch (\Throwable $exception) {
            throw new ClientDatabaseReadFailed(previous: $exception);
        } finally {
            $connection?->close();
        }
    }

    public function rows(ClientDatabaseCredentials $credentials, string $tableName, BrowseRowsQuery $query): RowPage
    {
        $connection = null;
        try {
            $connection = $this->connect($credentials);
            $table = $this->findTable($this->readSchema($connection, $credentials->database), $tableName);
            $this->validateQuery($table, $query);
            $quotedTable = $connection->quoteIdentifier($table->name);

            $rowsQuery = $connection->createQueryBuilder()->select('*')->from($quotedTable);
            $countQuery = $connection->createQueryBuilder()->select('COUNT(*)')->from($quotedTable);
            $this->applyFilters($connection, $rowsQuery, $query);
            $this->applyFilters($connection, $countQuery, $query);

            if (null !== $query->sort) {
                $rowsQuery->orderBy($connection->quoteIdentifier($query->sort), strtoupper($query->direction));
            } else {
                foreach ($table->primaryKey as $primaryColumn) {
                    $rowsQuery->addOrderBy($connection->quoteIdentifier($primaryColumn), 'ASC');
                }
            }

            $rowsQuery->setFirstResult(($query->page - 1) * $query->pageSize)->setMaxResults($query->pageSize);
            $rows = array_map($this->normalizeRow(...), $rowsQuery->executeQuery()->fetchAllAssociative());
            $total = (int) $countQuery->executeQuery()->fetchOne();

            return new RowPage($table, $rows, $query->page, $query->pageSize, $total);
        } catch (UnknownSchemaIdentifier $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ClientDatabaseReadFailed(previous: $exception);
        } finally {
            $connection?->close();
        }
    }

    /** @return list<SchemaTable> */
    private function readSchema(Connection $connection, string $database): array
    {
        $tableRows = $connection->executeQuery(
            'SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$database],
        )->fetchAllAssociative();
        $columnRows = $connection->executeQuery(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$database],
        )->fetchAllAssociative();
        $primaryRows = $connection->executeQuery(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION",
            [$database],
        )->fetchAllAssociative();

        /** @var array<string, list<SchemaColumn>> $columns */
        $columns = [];
        foreach ($columnRows as $row) {
            $table = (string) $row['TABLE_NAME'];
            $extra = (string) $row['EXTRA'];
            $columns[$table][] = new SchemaColumn(
                (string) $row['COLUMN_NAME'],
                (string) $row['COLUMN_TYPE'],
                'YES' === $row['IS_NULLABLE'],
                str_contains($extra, 'auto_increment'),
                str_contains($extra, 'GENERATED'),
            );
        }

        /** @var array<string, list<string>> $primaryKeys */
        $primaryKeys = [];
        foreach ($primaryRows as $row) {
            $primaryKeys[(string) $row['TABLE_NAME']][] = (string) $row['COLUMN_NAME'];
        }

        return array_map(
            static fn (array $row): SchemaTable => new SchemaTable(
                (string) $row['TABLE_NAME'],
                'VIEW' === $row['TABLE_TYPE'] ? 'view' : 'table',
                $columns[(string) $row['TABLE_NAME']] ?? [],
                $primaryKeys[(string) $row['TABLE_NAME']] ?? [],
            ),
            $tableRows,
        );
    }

    /** @param list<SchemaTable> $tables */
    private function findTable(array $tables, string $name): SchemaTable
    {
        foreach ($tables as $table) {
            if ($table->name === $name) {
                return $table;
            }
        }

        throw new UnknownSchemaIdentifier();
    }

    private function validateQuery(SchemaTable $table, BrowseRowsQuery $query): void
    {
        if (null !== $query->sort && !$table->hasColumn($query->sort)) {
            throw new UnknownSchemaIdentifier();
        }
        foreach (array_keys($query->filter) as $column) {
            if (!$table->hasColumn($column)) {
                throw new UnknownSchemaIdentifier();
            }
        }
    }

    private function applyFilters(Connection $connection, QueryBuilder $builder, BrowseRowsQuery $query): void
    {
        foreach ($query->filter as $column => $value) {
            $identifier = $connection->quoteIdentifier($column);
            if ('__NULL__' === $value) {
                $builder->andWhere($identifier.' IS NULL');
            } else {
                $parameter = 'filter_'.count($builder->getParameters());
                $builder->andWhere($identifier.' = :'.$parameter)->setParameter($parameter, $value);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $column => $value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $row[$column] = ['encoding' => 'base64', 'value' => base64_encode($value)];
            }
        }

        return $row;
    }

    private function connect(ClientDatabaseCredentials $credentials): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $credentials->host,
            'port' => $credentials->port,
            'dbname' => $credentials->database,
            'user' => $credentials->username,
            'password' => $credentials->password,
            'charset' => 'utf8mb4',
            'driverOptions' => [\PDO::ATTR_TIMEOUT => 5],
        ]);
    }
}
