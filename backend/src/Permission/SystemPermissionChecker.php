<?php

namespace App\Permission;

use App\Entity\ClientConnection;
use App\Entity\User;
use App\Repository\UserDatabaseAccessRepository;
use App\Repository\UserTablePermissionRepository;
use App\User\UserRole;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PermissionChecker::class)]
final class SystemPermissionChecker implements PermissionChecker
{
    public function __construct(
        private readonly UserDatabaseAccessRepository $accesses,
        private readonly UserTablePermissionRepository $tablePermissions,
    ) {
    }

    public function isAllowed(User $user, ClientConnection $connection, string $tableName, PermissionOperation $operation): bool
    {
        if (!$user->isActive() || !$connection->isActive()) {
            return false;
        }

        if (UserRole::Administrator === $user->getRole()) {
            return true;
        }

        $access = $this->accesses->findAssignment($user, $connection);
        if (null === $access) {
            return false;
        }

        $rule = $this->tablePermissions->findRule($access, $tableName);

        return $rule?->decisionFor($operation) ?? $access->getMode()->defaultDecision();
    }
}
