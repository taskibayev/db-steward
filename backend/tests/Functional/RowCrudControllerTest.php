<?php

namespace App\Tests\Functional;

use App\Connection\CredentialCipher;
use App\Entity\AuditOperation;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use App\Permission\AccessMode;
use App\Repository\AuditOperationRepository;
use App\Tests\DatabaseWebTestCase;
use App\Tests\Support\FakeClientRowWriter;
use App\User\UserRole;

final class RowCrudControllerTest extends DatabaseWebTestCase
{
    public function testManagerUpdatesOneRowAndCreatesTypedAudit(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('PUT', $this->url($connection).'/one', [
            'primaryKey' => ['id' => 42],
            'values' => ['name' => 'After'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);

        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['operation' => [
            'action' => 'update',
            'status' => 'succeeded',
            'affectedRows' => 1,
            'primaryKey' => ['id' => ['type' => 'int', 'value' => 42]],
            'diff' => ['name' => [
                'before' => ['type' => 'varchar(100)', 'value' => 'Before'],
                'after' => ['type' => 'varchar(100)', 'value' => 'After'],
            ]],
        ]], $client->getResponse()->getContent());
        $stored = static::getContainer()->get(AuditOperationRepository::class)->findOneBy([]);
        self::assertInstanceOf(AuditOperation::class, $stored);

        $client->request('GET', '/api/connections/'.$connection->getId()->toRfc4122().'/history');
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains([
            'items' => [[
                'actor' => ['email' => 'manager@example.com'],
                'table' => 'customers',
                'action' => 'update',
            ]],
            'pagination' => ['total' => 1],
        ], $client->getResponse()->getContent());
    }

    public function testDeniedDeleteNeverCallsWriter(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultDeny);
        $csrf = $this->csrf($client);
        $client->jsonRequest('DELETE', $this->url($connection).'/one', [
            'primaryKey' => ['id' => 42],
            'confirmed' => true,
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, static::getContainer()->get(AuditOperationRepository::class)->findAll());
    }

    public function testConflictIsRecordedWithoutLeakingDriverDetails(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $writer = static::getContainer()->get(FakeClientRowWriter::class);
        self::assertInstanceOf(FakeClientRowWriter::class, $writer);
        $writer->rejection = 'row_not_found';
        $csrf = $this->csrf($client);
        $client->jsonRequest('PUT', $this->url($connection).'/one', [
            'primaryKey' => ['id' => 999],
            'values' => ['name' => 'Missing'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);

        self::assertResponseStatusCodeSame(409);
        self::assertJsonResponseContains(['error' => 'row_not_found'], $client->getResponse()->getContent());
        $stored = static::getContainer()->get(AuditOperationRepository::class)->findOneBy([]);
        self::assertInstanceOf(AuditOperation::class, $stored);
        self::assertSame('conflict', $stored->getStatus()->value);
    }

    /** @return array{\Symfony\Bundle\FrameworkBundle\KernelBrowser, ClientConnection} */
    private function managerClient(AccessMode $mode): array
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = $this->resetDatabase();
        $manager = new User('manager@example.com', UserRole::Manager);
        $cipher = static::getContainer()->get(CredentialCipher::class);
        $connection = new ClientConnection('Client', 'db', 3306, 'client', $cipher->encrypt('user'), $cipher->encrypt('password'));
        $access = new UserDatabaseAccess($manager, $connection, $mode);
        foreach ([$manager, $connection, $access] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($manager);

        return [$client, $connection];
    }

    private function csrf(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $client->request('GET', '/api/auth/csrf');
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['token']);

        return $response['token'];
    }

    private function url(ClientConnection $connection): string
    {
        return '/api/connections/'.$connection->getId()->toRfc4122().'/tables/customers/rows';
    }
}
