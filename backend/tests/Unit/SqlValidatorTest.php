<?php

namespace App\Tests\Unit;

use App\Sql\PhpMyAdminSqlValidator;
use App\Sql\SqlOperation;
use App\Sql\SqlRejected;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlValidatorTest extends TestCase
{
    private PhpMyAdminSqlValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PhpMyAdminSqlValidator();
    }

    public function testExtractsEveryJoinAndSubqueryTable(): void
    {
        $parsed = $this->validator->validate(
            'SELECT c.id FROM customers c JOIN orders o ON o.customer_id = c.id WHERE EXISTS (SELECT 1 FROM products p WHERE p.id = o.id)',
            'northwind',
        );

        self::assertSame(SqlOperation::Select, $parsed->operation);
        self::assertSame(['customers', 'orders', 'products'], $parsed->allTables());
        self::assertSame([], $parsed->writeTables);
    }

    public function testSeparatesInsertTargetFromSelectSources(): void
    {
        $parsed = $this->validator->validate('INSERT INTO archive (id) SELECT id FROM orders', 'northwind');

        self::assertSame(['archive'], $parsed->writeTables);
        self::assertSame(['orders'], $parsed->readTables);
    }

    #[DataProvider('forbiddenSql')]
    public function testRejectsUnsafeSql(string $sql, string $error): void
    {
        try {
            $this->validator->validate($sql, 'northwind');
            self::fail('The SQL should have been rejected.');
        } catch (SqlRejected $exception) {
            self::assertSame($error, $exception->errorCode);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function forbiddenSql(): iterable
    {
        yield 'multiple statements' => ['SELECT * FROM customers; DELETE FROM customers', 'sql_invalid'];
        yield 'DDL' => ['DROP TABLE customers', 'sql_operation_forbidden'];
        yield 'transaction' => ['COMMIT', 'sql_invalid'];
        yield 'other database' => ['SELECT * FROM other.customers', 'sql_database_forbidden'];
        yield 'system database' => ['SELECT * FROM mysql.user', 'sql_database_forbidden'];
        yield 'outfile' => ["SELECT * FROM customers INTO OUTFILE '/tmp/data'", 'sql_invalid'];
        yield 'locking select' => ['SELECT * FROM customers FOR UPDATE', 'sql_feature_forbidden'];
        yield 'file read' => ["SELECT LOAD_FILE('/etc/passwd') FROM customers", 'sql_feature_forbidden'];
        yield 'replace' => ['REPLACE INTO customers (id) VALUES (1)', 'sql_operation_forbidden'];
    }
}
