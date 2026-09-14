<?php

namespace App\Tests\Functional;

use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use App\Permission\AccessMode;
use App\Tests\DatabaseWebTestCase;
use App\User\UserRole;

final class ConnectionControllerTest extends DatabaseWebTestCase
{
    public function testManagerOnlySeesAssignedActiveConnections(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $manager = new User('manager@example.com', UserRole::Manager);
        $assigned = $this->connection('Assigned');
        $unassigned = $this->connection('Unassigned');
        $disabled = $this->connection('Disabled');
        $disabled->setActive(false);
        $access = new UserDatabaseAccess($manager, $assigned, AccessMode::DefaultDeny);
        $disabledAccess = new UserDatabaseAccess($manager, $disabled, AccessMode::DefaultAllow);
        foreach ([$manager, $assigned, $unassigned, $disabled, $access, $disabledAccess] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($manager);

        $client->request('GET', '/api/connections');

        self::assertResponseIsSuccessful();
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertCount(1, $response['items']);
        self::assertSame('Assigned', $response['items'][0]['name']);
    }

    public function testAdministratorSeesEveryActiveConnection(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $active = $this->connection('Active');
        $disabled = $this->connection('Disabled');
        $disabled->setActive(false);
        foreach ([$admin, $active, $disabled] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($admin);

        $client->request('GET', '/api/connections');

        self::assertResponseIsSuccessful();
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertCount(1, $response['items']);
        self::assertSame('Active', $response['items'][0]['name']);
    }

    private function connection(string $name): ClientConnection
    {
        return new ClientConnection($name, 'db.example.com', 3306, 'client', 'encrypted-user', 'encrypted-password');
    }
}
