<?php

namespace App\Permission;

use App\Entity\ClientConnection;
use App\Entity\User;

interface PermissionChecker
{
    public function canAccessDatabase(User $user, ClientConnection $connection): bool;

    public function isAllowed(User $user, ClientConnection $connection, string $tableName, PermissionOperation $operation): bool;
}
