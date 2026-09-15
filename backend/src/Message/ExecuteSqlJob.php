<?php

namespace App\Message;

final readonly class ExecuteSqlJob implements AsyncMessage
{
    public function __construct(public string $jobId)
    {
    }
}
