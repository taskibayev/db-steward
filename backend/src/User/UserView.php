<?php

namespace App\User;

use App\Entity\User;

final class UserView
{
    /** @return array<string, bool|string|null> */
    public static function fromEntity(User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'role' => $user->getRole()->value,
            'active' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
            'lastLoginAt' => $user->getLastLoginAt()?->format(DATE_ATOM),
        ];
    }
}
