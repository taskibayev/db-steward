<?php

namespace App\User;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';

    public function securityRole(): string
    {
        return match ($this) {
            self::Administrator => 'ROLE_ADMIN',
            self::Manager => 'ROLE_MANAGER',
        };
    }
}
