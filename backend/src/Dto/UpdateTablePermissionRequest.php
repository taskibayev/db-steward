<?php

namespace App\Dto;

final readonly class UpdateTablePermissionRequest
{
    public function __construct(
        public ?bool $select = null,
        public ?bool $insert = null,
        public ?bool $update = null,
        public ?bool $delete = null,
    ) {
    }
}
