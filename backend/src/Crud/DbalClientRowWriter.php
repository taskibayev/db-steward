<?php

namespace App\Crud;

use App\ClientDatabase\DbalClientConnectionFactory;
use App\ClientDatabase\DbalSchemaInspector;
use App\ClientDatabase\SchemaColumn;
use App\ClientDatabase\SchemaTable;
use App\ClientDatabase\UnknownSchemaIdentifier;
use App\Connection\ClientDatabaseCredentials;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ClientRowWriter::class)]
final class DbalClientRowWriter implements ClientRowWriter
{
    public function __construct(
        private readonly DbalClientConnectionFactory $connections,
        private readonly DbalSchemaInspector $schema,
    ) {
    }

    public function insert(ClientDatabaseCredentials $credentials, string $table, array $values): RowMutationResult
    {
        return $this->transactional($credentials, function (Connection $connection) use ($credentials, $table, $values): RowMutationResult {
            $metadata = $this->writableTable($connection, $credentials->database, $table);
            $this->validateValues($metadata, $values, true);
            if ([] === $values) {
                throw new RowMutationRejected('values_required');
            }

            $quotedValues = [];
            foreach ($values as $column => $value) {
                $quotedValues[$connection->quoteIdentifier($column)] = $value;
            }
            $affected = $connection->insert($connection->quoteIdentifier($metadata->name), $quotedValues);
            if (1 !== $affected) {
                throw new RowMutationRejected('unexpected_affected_rows');
            }

            $primaryKey = [];
            foreach ($metadata->primaryKey as $column) {
                if (array_key_exists($column, $values)) {
                    $primaryKey[$column] = $values[$column];
                    continue;
                }
                $definition = $this->column($metadata, $column);
                if (1 === count($metadata->primaryKey) && $definition->autoincrement) {
                    $primaryKey[$column] = $connection->lastInsertId();
                    continue;
                }
                throw new RowMutationRejected('primary_key_required');
            }
            $after = $this->findRow($connection, $metadata, $primaryKey, false);

            return new RowMutationResult($metadata, $primaryKey, null, $after, 1);
        });
    }

    public function update(ClientDatabaseCredentials $credentials, string $table, array $primaryKey, array $values): RowMutationResult
    {
        return $this->transactional($credentials, function (Connection $connection) use ($credentials, $table, $primaryKey, $values): RowMutationResult {
            $metadata = $this->writableTable($connection, $credentials->database, $table);
            $this->validatePrimaryKey($metadata, $primaryKey);
            $this->validateValues($metadata, $values, false);
            if ([] === $values) {
                throw new RowMutationRejected('values_required');
            }
            foreach ($metadata->primaryKey as $column) {
                if (array_key_exists($column, $values)) {
                    throw new RowMutationRejected('primary_key_not_editable');
                }
            }
            $before = $this->findRow($connection, $metadata, $primaryKey, true);
            foreach ($values as $column => $value) {
                if (($before[$column] ?? null) !== $value) {
                    $changed = true;
                    break;
                }
            }
            if (!isset($changed)) {
                throw new RowMutationRejected('no_changes');
            }

            $builder = $connection->createQueryBuilder()->update($connection->quoteIdentifier($metadata->name));
            foreach ($values as $column => $value) {
                $parameter = 'value_'.count($builder->getParameters());
                $builder->set($connection->quoteIdentifier($column), ':'.$parameter)->setParameter($parameter, $value);
            }
            $this->applyPrimaryKey($connection, $builder, $primaryKey);
            if (1 !== $builder->executeStatement()) {
                throw new RowMutationRejected('unexpected_affected_rows');
            }
            $after = $this->findRow($connection, $metadata, $primaryKey, false);

            return new RowMutationResult($metadata, $primaryKey, $before, $after, 1);
        });
    }

    public function delete(ClientDatabaseCredentials $credentials, string $table, array $primaryKey): RowMutationResult
    {
        return $this->transactional($credentials, function (Connection $connection) use ($credentials, $table, $primaryKey): RowMutationResult {
            $metadata = $this->writableTable($connection, $credentials->database, $table);
            $this->validatePrimaryKey($metadata, $primaryKey);
            $before = $this->findRow($connection, $metadata, $primaryKey, true);
            $builder = $connection->createQueryBuilder()->delete($connection->quoteIdentifier($metadata->name));
            $this->applyPrimaryKey($connection, $builder, $primaryKey);
            if (1 !== $builder->executeStatement()) {
                throw new RowMutationRejected('unexpected_affected_rows');
            }

            return new RowMutationResult($metadata, $primaryKey, $before, null, 1);
        });
    }

    private function writableTable(Connection $connection, string $database, string $table): SchemaTable
    {
        $metadata = $this->schema->table($connection, $database, $table);
        if ($metadata->isReadOnly()) {
            throw new RowMutationRejected('table_read_only');
        }

        return $metadata;
    }

    /** @param array<string, mixed> $values */
    private function validateValues(SchemaTable $table, array $values, bool $insert): void
    {
        foreach ($values as $name => $value) {
            $column = $this->column($table, $name);
            if ($column->generated || $column->autoincrement || preg_match('/(?:binary|blob)/i', $column->type)) {
                throw new RowMutationRejected('column_not_editable');
            }
            if (null === $value && !$column->nullable) {
                throw new RowMutationRejected('null_not_allowed');
            }
            if (!is_scalar($value) && null !== $value) {
                throw new RowMutationRejected('invalid_value');
            }
        }
        if ($insert) {
            foreach ($table->columns as $column) {
                if (!$column->nullable && !$column->autoincrement && !$column->generated && !$column->hasDefault && !array_key_exists($column->name, $values)) {
                    throw new RowMutationRejected('required_column_missing');
                }
            }
        }
    }

    private function column(SchemaTable $table, string $name): SchemaColumn
    {
        foreach ($table->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }
        throw new RowMutationRejected('column_not_found');
    }

    /** @param array<string, mixed> $primaryKey */
    private function validatePrimaryKey(SchemaTable $table, array $primaryKey): void
    {
        $expected = $table->primaryKey;
        $actual = array_keys($primaryKey);
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new RowMutationRejected('invalid_primary_key');
        }
    }

    /**
     * @param array<string, mixed> $primaryKey
     *
     * @return array<string, mixed>
     */
    private function findRow(Connection $connection, SchemaTable $table, array $primaryKey, bool $lock): array
    {
        $builder = $connection->createQueryBuilder()->select('*')->from($connection->quoteIdentifier($table->name));
        $this->applyPrimaryKey($connection, $builder, $primaryKey);
        if ($lock) {
            $builder->forUpdate();
        }
        $rows = $builder->setMaxResults(2)->executeQuery()->fetchAllAssociative();
        if (1 !== count($rows)) {
            throw new RowMutationRejected([] === $rows ? 'row_not_found' : 'primary_key_not_unique');
        }

        return $rows[0];
    }

    /** @param array<string, mixed> $primaryKey */
    private function applyPrimaryKey(Connection $connection, QueryBuilder $builder, array $primaryKey): void
    {
        foreach ($primaryKey as $column => $value) {
            $parameter = 'pk_'.count($builder->getParameters());
            $identifier = $connection->quoteIdentifier($column);
            if (null === $value) {
                $builder->andWhere($identifier.' IS NULL');
            } else {
                $builder->andWhere($identifier.' = :'.$parameter)->setParameter($parameter, $value);
            }
        }
    }

    /**
     * @template T
     *
     * @param \Closure(Connection): T $operation
     *
     * @return T
     */
    private function transactional(ClientDatabaseCredentials $credentials, \Closure $operation): mixed
    {
        $connection = null;
        try {
            $connection = $this->connections->create($credentials);

            return $connection->transactional($operation);
        } catch (RowMutationRejected $exception) {
            throw $exception;
        } catch (UnknownSchemaIdentifier) {
            throw new RowMutationRejected('table_not_found');
        } catch (\Throwable $exception) {
            throw new RowMutationFailed($exception);
        } finally {
            $connection?->close();
        }
    }
}
