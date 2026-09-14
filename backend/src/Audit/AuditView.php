<?php

namespace App\Audit;

use App\Entity\AuditOperation;

final class AuditView
{
    /** @return array<string, mixed> */
    public static function fromEntity(AuditOperation $operation): array
    {
        return [
            'id' => $operation->getId()->toRfc4122(),
            'action' => $operation->getAction()->value,
            'status' => $operation->getStatus()->value,
            'table' => $operation->getTableName(),
            'actor' => [
                'id' => $operation->getActor()->getId()->toRfc4122(),
                'email' => $operation->getActor()->getEmail(),
            ],
            'connection' => [
                'id' => $operation->getConnection()->getId()->toRfc4122(),
                'name' => $operation->getConnection()->getName(),
                'database' => $operation->getConnection()->getDatabaseName(),
            ],
            'primaryKey' => $operation->getPrimaryKey(),
            'affectedRows' => $operation->getAffectedRows(),
            'correlationId' => $operation->getCorrelationId(),
            'error' => $operation->getErrorCode(),
            'before' => $operation->getSnapshot()?->getBeforeData(),
            'after' => $operation->getSnapshot()?->getAfterData(),
            'diff' => $operation->getSnapshot()?->getDiff(),
            'createdAt' => $operation->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
