<?php

namespace App\Tests\Functional;

use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use App\Entity\UserTablePermission;
use App\Permission\AccessMode;
use App\Permission\PermissionOperation;
use App\Permission\SystemPermissionChecker;
use App\Repository\UserDatabaseAccessRepository;
use App\Repository\UserTablePermissionRepository;
use App\Tests\DatabaseWebTestCase;
use App\User\UserRole;

final class AdminAccessControllerTest extends DatabaseWebTestCase
{
    public function testAdministratorAssignsManagerAndSetsTableOverrides(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $manager = new User('manager@example.com', UserRole::Manager);
        $connection = $this->connection('Client A');
        $entityManager->persist($admin);
        $entityManager->persist($manager);
        $entityManager->persist($connection);
        $entityManager->flush();
        $client->loginUser($admin);
        $csrf = $this->csrfToken($client);

        $client->jsonRequest('POST', '/api/admin/access', [
            'userId' => $manager->getId()->toRfc4122(),
            'connectionId' => $connection->getId()->toRfc4122(),
            'mode' => 'default_deny',
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);

        self::assertResponseStatusCodeSame(201);
        $created = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsArray($created['access']);
        self::assertSame('default_deny', $created['access']['mode']);
        self::assertSame('manager@example.com', $created['access']['user']['email']);
        self::assertIsString($created['access']['id']);

        $client->jsonRequest('PUT', '/api/admin/access/'.$created['access']['id'].'/tables/orders', [
            'select' => true,
            'insert' => false,
            'update' => null,
            'delete' => null,
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);

        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains([
            'tablePermission' => [
                'table' => 'orders',
                'select' => true,
                'insert' => false,
                'update' => null,
                'delete' => null,
            ],
        ], $client->getResponse()->getContent());

        $client->request('GET', '/api/admin/access');
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['items' => [['tablePermissions' => [['table' => 'orders']]]]], $client->getResponse()->getContent());
    }

    public function testDuplicateAssignmentIsRejectedAndManagerCannotManageAccess(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $manager = new User('manager@example.com', UserRole::Manager);
        $connection = $this->connection('Client A');
        $access = new UserDatabaseAccess($manager, $connection, AccessMode::DefaultAllow);
        foreach ([$admin, $manager, $connection, $access] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($admin);
        $csrf = $this->csrfToken($client);

        $client->jsonRequest('POST', '/api/admin/access', [
            'userId' => $manager->getId()->toRfc4122(),
            'connectionId' => $connection->getId()->toRfc4122(),
            'mode' => 'default_deny',
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(409);

        $client->loginUser($manager);
        $client->request('GET', '/api/admin/access');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPermissionCheckerUsesDefaultAndPerTableDecision(): void
    {
        static::createClient();
        $entityManager = $this->resetDatabase();
        $manager = new User('manager@example.com', UserRole::Manager);
        $connection = $this->connection('Client A');
        $access = new UserDatabaseAccess($manager, $connection, AccessMode::DefaultDeny);
        $rule = new UserTablePermission($access, 'orders');
        $rule->update(true, null, false, null);
        foreach ([$manager, $connection, $access, $rule] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $accesses = $entityManager->getRepository(UserDatabaseAccess::class);
        $tablePermissions = $entityManager->getRepository(UserTablePermission::class);
        self::assertInstanceOf(UserDatabaseAccessRepository::class, $accesses);
        self::assertInstanceOf(UserTablePermissionRepository::class, $tablePermissions);
        $checker = new SystemPermissionChecker($accesses, $tablePermissions);
        self::assertTrue($checker->isAllowed($manager, $connection, 'orders', PermissionOperation::Select));
        self::assertFalse($checker->isAllowed($manager, $connection, 'orders', PermissionOperation::Update));
        self::assertFalse($checker->isAllowed($manager, $connection, 'orders', PermissionOperation::Delete));
        self::assertFalse($checker->isAllowed($manager, $connection, 'customers', PermissionOperation::Select));

        $access->setMode(AccessMode::DefaultAllow);
        $entityManager->flush();
        self::assertTrue($checker->isAllowed($manager, $connection, 'customers', PermissionOperation::Delete));
    }

    private function connection(string $name): ClientConnection
    {
        return new ClientConnection($name, 'db.example.com', 3306, 'client', 'encrypted-user', 'encrypted-password');
    }

    /** @param \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
    private function csrfToken(object $client): string
    {
        $client->request('GET', '/api/auth/csrf');
        $csrf = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($csrf['token']);

        return $csrf['token'];
    }
}
