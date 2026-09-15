<?php

namespace App\Tests\Support;

use App\Connection\ClientDatabaseCredentials;
use App\Sql\ClientSqlExecutor;
use App\Sql\ParsedSql;
use App\Sql\SqlOperation;
use App\Sql\SqlQueryResult;

final class FakeClientSqlExecutor implements ClientSqlExecutor
{
    public int $calls = 0;

    public function execute(ClientDatabaseCredentials $credentials, ParsedSql $sql): SqlQueryResult
    {
        ++$this->calls;
        if (SqlOperation::Select === $sql->operation) {
            return new SqlQueryResult(['id', 'name'], [['id' => 1, 'name' => 'Northwind']], false, null);
        }

        return new SqlQueryResult([], [], false, 7);
    }
}
