<?php

namespace App\Sql;

interface SqlValidator
{
    public function validate(string $sql, string $database): ParsedSql;
}
