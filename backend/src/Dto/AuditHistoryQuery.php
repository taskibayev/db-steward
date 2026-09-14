<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AuditHistoryQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Choice(choices: [25, 50, 100])]
        public int $pageSize = 25,
    ) {
    }
}
