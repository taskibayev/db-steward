<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSqlJobRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100000)]
        public string $sql = '',
        public bool $writeAcknowledged = false,
    ) {
    }
}
