<?php

namespace App\Sql;

use App\Connection\ClientDatabaseCredentials;

interface ClientSqlExecutor
{
    public function execute(ClientDatabaseCredentials $credentials, ParsedSql $sql): SqlQueryResult;
}
