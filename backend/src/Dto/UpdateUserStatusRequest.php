<?php

namespace App\Dto;

final readonly class UpdateUserStatusRequest
{
    public function __construct(public bool $active)
    {
    }
}
