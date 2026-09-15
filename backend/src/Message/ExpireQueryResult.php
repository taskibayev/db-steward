<?php

namespace App\Message;

final readonly class ExpireQueryResult implements AsyncMessage
{
    public function __construct(public string $resultId)
    {
    }
}
