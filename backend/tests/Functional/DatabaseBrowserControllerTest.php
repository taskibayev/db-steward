<?php

namespace App\Tests\Functional;

use App\Connection\CredentialCipher;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use App\Entity\UserTablePermission;
use App\Permission\AccessMode;
use App\Tests\DatabaseWebTestCase;
use App\Tests\Support\FakeClientDatabaseReader;
use App\User\UserRole;

final class DatabaseBrowserControllerTest extends DatabaseWebTestCase
{
    public function testManagerSeesOnlySelectableSchemaAndBrowsesRows(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $manager = new User('manager@example.com', UserRole::Manager);
        $connection = $this->connection();
        $access = new UserDatabaseAccess($manager, $connection, AccessMode::DefaultDeny);
        $orders = new UserTablePermission($access, 'orders');
        $orders->update(true, null, null, null);
        foreach ([$manager, $connection, $access, $orders] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($manager);
        $baseUrl = '/api/connections/'.$connection->getId()->toRfc4122();

        $client->request('GET', $baseUrl.'/schema');
        self::assertResponseIsSuccessful();
        $schema = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertCount(1, $schema['items']);
        self::assertJsonResponseContains(['items' => [[
            'name' => 'orders',
            'kind' => 'table',
            'primaryKey' => ['id'],
            'readOnly' => false,
        ]]], $client->getResponse()->getContent());

        $client->request('GET', $baseUrl.'/tables/orders/rows?page=1&pageSize=25&sort=id&direction=desc&filter[customer]=Acme');
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains([
            'rows' => [['id' => 1, 'customer' => 'Acme']],
            'pagination' => ['page' => 1, 'pageSize' => 25, 'total' => 1, 'pages' => 1],
        ], $client->getResponse()->getContent());

        $client->request('GET', $baseUrl.'/tables/report/rows');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnassignedManagerCannotDiscoverDatabaseSchema(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $manager = new User('manager@example.com', UserRole::Manager);
        $connection = $this->connection();
        $entityManager->persist($manager);
        $entityManager->persist($connection);
        $entityManager->flush();
        $client->loginUser($manager);

        $client->request('GET', '/api/connections/'.$connection->getId()->toRfc4122().'/schema');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanBrowseAViewMarkedReadOnly(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $connection = $this->connection();
        $entityManager->persist($admin);
        $entityManager->persist($connection);
        $entityManager->flush();
        $client->loginUser($admin);

        $client->request('GET', '/api/connections/'.$connection->getId()->toRfc4122().'/schema');

        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['items' => [1 => ['name' => 'report', 'readOnly' => true]]], $client->getResponse()->getContent());
    }

    public function testClientDriverFailureReturnsSafeError(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $connection = $this->connection();
        $entityManager->persist($admin);
        $entityManager->persist($connection);
        $entityManager->flush();
        $client->loginUser($admin);
        $reader = static::getContainer()->get(FakeClientDatabaseReader::class);
        self::assertInstanceOf(FakeClientDatabaseReader::class, $reader);
        $reader->fail = true;

        $client->request('GET', '/api/connections/'.$connection->getId()->toRfc4122().'/schema');

        self::assertResponseStatusCodeSame(502);
        self::assertJsonResponseContains(['error' => 'client_database_unavailable'], $client->getResponse()->getContent());
    }

    private function connection(): ClientConnection
    {
        $cipher = static::getContainer()->get(CredentialCipher::class);

        return new ClientConnection(
            'Client',
            'db.example.com',
            3306,
            'client',
            $cipher->encrypt('client-user'),
            $cipher->encrypt('client-password'),
        );
    }
}
