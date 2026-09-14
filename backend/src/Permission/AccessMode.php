<?php

namespace App\Permission;

enum AccessMode: string
{
    case DefaultDeny = 'default_deny';
    case DefaultAllow = 'default_allow';

    public function defaultDecision(): bool
    {
        return self::DefaultAllow === $this;
    }
}
