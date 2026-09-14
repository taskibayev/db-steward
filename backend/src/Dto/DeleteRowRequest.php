<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeleteRowRequest
{
    /** @param array<string, mixed> $primaryKey */
    public function __construct(
        #[Assert\Count(min: 1)]
        public array $primaryKey,
        #[Assert\IsTrue(message: 'Delete confirmation is required.')]
        public bool $confirmed,
    ) {
    }
}
