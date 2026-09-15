<?php

namespace App\Audit;

enum AuditAction: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
    case UndoInsert = 'undo_insert';
    case UndoUpdate = 'undo_update';
    case UndoDelete = 'undo_delete';

    public function isUndo(): bool
    {
        return str_starts_with($this->value, 'undo_');
    }
}
