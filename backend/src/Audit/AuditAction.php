<?php

namespace App\Audit;

enum AuditAction: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
}
