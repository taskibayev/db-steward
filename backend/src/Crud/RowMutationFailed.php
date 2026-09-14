<?php

namespace App\Crud;

final class RowMutationFailed extends \RuntimeException
{
    public function __construct(\Throwable $previous)
    {
        parent::__construct('client_write_failed', previous: $previous);
    }
}
