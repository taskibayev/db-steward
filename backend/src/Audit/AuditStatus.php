<?php

namespace App\Audit;

enum AuditStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Conflict = 'conflict';
}
