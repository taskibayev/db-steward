<?php

namespace App\Sql;

use App\Permission\PermissionOperation;

enum SqlOperation: string
{
    case Select = 'select';
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';

    public function isWrite(): bool
    {
        return self::Select !== $this;
    }

    public function permission(): PermissionOperation
    {
        return PermissionOperation::from($this->value);
    }
}
