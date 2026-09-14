<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateRowRequest
{
    /**
     * @param array<string, mixed> $primaryKey
     * @param array<string, mixed> $values
     */
    public function __construct(
        #[Assert\Count(min: 1)]
        public array $primaryKey,
        #[Assert\Count(min: 1)]
        public array $values,
    ) {
    }
}
