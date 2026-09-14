<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateConnectionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $host,
        #[Assert\Range(min: 1, max: 65535)]
        public int $port,
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        #[Assert\Regex(pattern: '/^[A-Za-z0-9_$-]+$/D')]
        public string $database,
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $username,
        #[Assert\Length(max: 4096)]
        public string $password,
    ) {
    }
}
