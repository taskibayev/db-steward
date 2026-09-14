<?php

namespace App\Dto;

final readonly class UpdateConnectionStatusRequest
{
    public function __construct(public bool $active)
    {
    }
}
