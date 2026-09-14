<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateDatabaseAccessRequest
{
    public function __construct(
        #[Assert\Uuid]
        public string $userId,
        #[Assert\Uuid]
        public string $connectionId,
        #[Assert\Choice(choices: ['default_deny', 'default_allow'])]
        public string $mode,
    ) {
    }
}
