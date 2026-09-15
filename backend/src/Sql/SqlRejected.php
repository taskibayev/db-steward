<?php

namespace App\Sql;

final class SqlRejected extends \RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
