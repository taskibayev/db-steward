<?php

namespace App\Sql;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Exceptions\ParserException;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SqlValidator::class)]
final class PhpMyAdminSqlValidator implements SqlValidator
{
    private const SYSTEM_DATABASES = ['information_schema', 'mysql', 'performance_schema', 'sys'];

    public function validate(string $sql, string $database): ParsedSql
    {
        $normalized = trim($sql);
        if ('' === $normalized || strlen($normalized) > 100_000) {
            throw new SqlRejected('sql_invalid');
        }
        try {
            $parser = new Parser($normalized, true);
        } catch (ParserException) {
            throw new SqlRejected('sql_invalid');
        }
        if ([] !== $parser->errors || 1 !== count($parser->statements)) {
            throw new SqlRejected('sql_invalid');
        }
        if (1 === preg_match('/\bLOAD_FILE\s*\(/i', $normalized)) {
            throw new SqlRejected('sql_feature_forbidden');
        }
        $statement = $parser->statements[0];
        $operation = match (true) {
            $statement instanceof SelectStatement => SqlOperation::Select,
            $statement instanceof InsertStatement => SqlOperation::Insert,
            $statement instanceof UpdateStatement => SqlOperation::Update,
            $statement instanceof DeleteStatement => SqlOperation::Delete,
            default => throw new SqlRejected('sql_operation_forbidden'),
        };
        $this->assertSafeShape($statement);

        [$writeTables, $readTables] = $this->tables($statement, $operation, $database);
        foreach ($this->subqueries($normalized) as $subquery) {
            $parsed = $this->validate($subquery, $database);
            if (SqlOperation::Select !== $parsed->operation) {
                throw new SqlRejected('sql_operation_forbidden');
            }
            $readTables = [...$readTables, ...$parsed->readTables];
        }
        $writeTables = array_values(array_unique($writeTables));
        $readTables = array_values(array_unique($readTables));
        if ([] === [...$writeTables, ...$readTables]) {
            throw new SqlRejected('sql_table_required');
        }

        return new ParsedSql(rtrim($normalized, "; \t\n\r\0\x0B"), $operation, $writeTables, $readTables);
    }

    private function assertSafeShape(Statement $statement): void
    {
        if ($statement instanceof SelectStatement) {
            if (null !== $statement->into || null !== $statement->procedure || null !== $statement->end_options) {
                throw new SqlRejected('sql_feature_forbidden');
            }
        }
        if ($statement instanceof InsertStatement && (null !== $statement->onDuplicateSet || null !== $statement->with)) {
            throw new SqlRejected('sql_feature_forbidden');
        }
    }

    /** @return array{list<string>, list<string>} */
    private function tables(Statement $statement, SqlOperation $operation, string $database): array
    {
        $top = [];
        if ($statement instanceof SelectStatement) {
            $top = $this->fromAndJoins($statement->from, $statement->join, $database);
            foreach ($statement->union as $union) {
                $top = [...$top, ...$this->fromAndJoins($union->from, $union->join, $database)];
            }

            return [[], $top];
        }
        if ($statement instanceof InsertStatement) {
            $target = $statement->into?->dest;
            if (!$target instanceof Expression) {
                throw new SqlRejected('sql_table_required');
            }
            $writes = [$this->tableName($target, $database)];
            $reads = $statement->select instanceof SelectStatement
                ? $this->fromAndJoins($statement->select->from, $statement->select->join, $database)
                : [];

            return [$writes, $reads];
        }
        if ($statement instanceof UpdateStatement) {
            return [$this->fromAndJoins($statement->tables, $statement->join, $database), []];
        }
        if ($statement instanceof DeleteStatement) {
            return [$this->fromAndJoins($statement->from ?? [], $statement->join, $database), []];
        }

        throw new SqlRejected('sql_operation_forbidden');
    }

    /**
     * @param array<int, Expression>|null $from
     * @param array<int, object>|null     $joins
     *
     * @return list<string>
     */
    private function fromAndJoins(?array $from, ?array $joins, string $database): array
    {
        $tables = [];
        foreach ($from ?? [] as $expression) {
            if (null !== $expression->table) {
                $tables[] = $this->tableName($expression, $database);
            }
        }
        foreach ($joins ?? [] as $join) {
            if (property_exists($join, 'expr') && $join->expr instanceof Expression && null !== $join->expr->table) {
                $tables[] = $this->tableName($join->expr, $database);
            }
        }

        return $tables;
    }

    private function tableName(Expression $expression, string $database): string
    {
        $qualifiedDatabase = $expression->database;
        if (null !== $qualifiedDatabase && (in_array(mb_strtolower($qualifiedDatabase), self::SYSTEM_DATABASES, true) || 0 !== strcasecmp($qualifiedDatabase, $database))) {
            throw new SqlRejected('sql_database_forbidden');
        }
        if (null === $expression->table || '' === $expression->table) {
            throw new SqlRejected('sql_table_required');
        }

        return $expression->table;
    }

    /** @return list<string> */
    private function subqueries(string $sql): array
    {
        $result = [];
        $stack = [];
        $quote = null;
        $length = strlen($sql);
        for ($index = 0; $index < $length; ++$index) {
            $char = $sql[$index];
            if (null !== $quote) {
                if ('\\' === $char) {
                    ++$index;
                } elseif ($char === $quote) {
                    if ('`' === $quote && ($sql[$index + 1] ?? '') === '`') {
                        ++$index;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
            } elseif ('(' === $char) {
                $stack[] = $index;
            } elseif (')' === $char && [] !== $stack) {
                $start = array_pop($stack);
                $candidate = trim(substr($sql, $start + 1, $index - $start - 1));
                if (1 === preg_match('/^SELECT\b/i', $candidate)) {
                    $result[] = $candidate;
                }
            }
        }

        return array_values(array_unique($result));
    }
}
