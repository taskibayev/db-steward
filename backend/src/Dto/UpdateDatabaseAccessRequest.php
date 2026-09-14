<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateDatabaseAccessRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['default_deny', 'default_allow'])]
        public string $mode,
    ) {
    }
}
