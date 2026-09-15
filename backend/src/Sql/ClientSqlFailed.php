<?php

namespace App\Sql;

final class ClientSqlFailed extends \RuntimeException
{
    public function __construct(\Throwable $previous)
    {
        parent::__construct('client_sql_failed', previous: $previous);
    }
}
