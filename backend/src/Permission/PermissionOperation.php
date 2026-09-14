<?php

namespace App\Permission;

enum PermissionOperation: string
{
    case Select = 'select';
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
}
