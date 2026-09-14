<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BrowseRowsQuery
{
    /** @param array<string, string> $filter */
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Choice(choices: [25, 50, 100])]
        public int $pageSize = 25,
        public ?string $sort = null,
        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $direction = 'asc',
        public array $filter = [],
    ) {
    }
}
