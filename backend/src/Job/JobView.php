<?php

namespace App\Job;

use App\Entity\Job;
use App\Entity\TemporaryQueryResult;

final class JobView
{
    /** @return array<string, mixed> */
    public static function fromEntity(Job $job, ?TemporaryQueryResult $result = null): array
    {
        $execution = $job->getSqlExecution();

        return [
            'id' => $job->getId()->toRfc4122(),
            'status' => $job->getStatus()->value,
            'actor' => ['id' => $job->getActor()->getId()->toRfc4122(), 'email' => $job->getActor()->getEmail()],
            'connection' => ['id' => $job->getConnection()->getId()->toRfc4122(), 'name' => $job->getConnection()->getName(), 'database' => $job->getConnection()->getDatabaseName()],
            'operation' => $execution?->getOperation()->value,
            'sql' => $execution?->getSqlText(),
            'tables' => $execution?->getTables(),
            'affectedRows' => $execution?->getAffectedRows(),
            'error' => $job->getErrorCode(),
            'correlationId' => $job->getCorrelationId(),
            'createdAt' => $job->getCreatedAt()->format(DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(DATE_ATOM),
            'completedAt' => $job->getCompletedAt()?->format(DATE_ATOM),
            'result' => null === $result ? null : [
                'columns' => $result->getColumns(),
                'rows' => $result->getRows(),
                'rowCount' => $result->getRowCount(),
                'truncated' => $result->isTruncated(),
                'expiresAt' => $result->getExpiresAt()->format(DATE_ATOM),
            ],
        ];
    }
}
