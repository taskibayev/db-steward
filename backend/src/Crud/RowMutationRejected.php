<?php

namespace App\Crud;

final class RowMutationRejected extends \DomainException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
