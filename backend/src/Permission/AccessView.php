<?php

namespace App\Permission;

use App\Connection\ConnectionView;
use App\Entity\UserDatabaseAccess;
use App\Entity\UserTablePermission;
use App\User\UserView;

final class AccessView
{
    /** @return array<string, mixed> */
    public static function fromEntity(UserDatabaseAccess $access): array
    {
        return [
            'id' => $access->getId()->toRfc4122(),
            'mode' => $access->getMode()->value,
            'user' => UserView::fromEntity($access->getUser()),
            'connection' => ConnectionView::fromEntity($access->getConnection()),
            'tablePermissions' => array_map(
                self::tablePermission(...),
                $access->getTablePermissions()->toArray(),
            ),
            'createdAt' => $access->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $access->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, bool|string|null> */
    public static function tablePermission(UserTablePermission $permission): array
    {
        return [
            'id' => $permission->getId()->toRfc4122(),
            'table' => $permission->getTableName(),
            'select' => $permission->getSelectAllowed(),
            'insert' => $permission->getInsertAllowed(),
            'update' => $permission->getUpdateAllowed(),
            'delete' => $permission->getDeleteAllowed(),
        ];
    }
}
