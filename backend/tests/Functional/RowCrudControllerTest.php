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

    public function testManagerCanUndoOwnUpdateOnlyOnce(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('PUT', $this->url($connection).'/one', [
            'primaryKey' => ['id' => 42],
            'values' => ['name' => 'After'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $created = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsArray($created['operation']);
        self::assertIsString($created['operation']['id']);

        $client->jsonRequest('POST', '/api/audit/'.$created['operation']['id'].'/undo', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['operation' => [
            'action' => 'undo_update',
            'status' => 'succeeded',
            'undoesOperationId' => $created['operation']['id'],
        ]], $client->getResponse()->getContent());

        $client->jsonRequest('POST', '/api/audit/'.$created['operation']['id'].'/undo', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(409);
        self::assertJsonResponseContains(['operation' => [
            'action' => 'undo_update',
            'status' => 'conflict',
            'error' => 'operation_already_undone',
        ]], $client->getResponse()->getContent());
        self::assertCount(3, static::getContainer()->get(AuditOperationRepository::class)->findAll());
    }

    public function testManagerCannotUndoAnotherManagersOperation(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('DELETE', $this->url($connection).'/one', [
            'primaryKey' => ['id' => 42],
            'confirmed' => true,
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $created = self::decodeJsonResponse($client->getResponse()->getContent());

        $entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $entityManager->find(ClientConnection::class, $connection->getId());
        self::assertInstanceOf(ClientConnection::class, $connection);
        $other = new User('other@example.com', UserRole::Manager);
        $entityManager->persist($other);
        $entityManager->persist(new UserDatabaseAccess($other, $connection, AccessMode::DefaultAllow));
        $entityManager->flush();
        $client->loginUser($other);
        $csrf = $this->csrf($client);
        self::assertIsArray($created['operation']);
        self::assertIsString($created['operation']['id']);
        $client->jsonRequest('POST', '/api/audit/'.$created['operation']['id'].'/undo', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, static::getContainer()->get(AuditOperationRepository::class)->findAll());
    }

    public function testManagerCanUndoInsertAndDelete(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);

        $client->jsonRequest('POST', $this->url($connection), [
            'values' => ['name' => 'After'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $insert = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsArray($insert['operation']);
        self::assertIsString($insert['operation']['id']);
        $client->jsonRequest('POST', '/api/audit/'.$insert['operation']['id'].'/undo', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['operation' => ['action' => 'undo_insert', 'status' => 'succeeded']], $client->getResponse()->getContent());

        $client->jsonRequest('DELETE', $this->url($connection).'/one', [
            'primaryKey' => ['id' => 42],
            'confirmed' => true,
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $delete = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsArray($delete['operation']);
        self::assertIsString($delete['operation']['id']);
        $client->jsonRequest('POST', '/api/audit/'.$delete['operation']['id'].'/undo', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['operation' => [
            'action' => 'undo_delete',
            'status' => 'succeeded',
            'primaryKey' => ['id' => ['type' => 'int', 'value' => 42]],
        ]], $client->getResponse()->getContent());
    }

    public function testUndoDataConflictIsAuditedAndCanBeRetried(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('PUT', $this->url($connection).'/one', [
            'primaryKey' => ['id' => 42],
            'values' => ['name' => 'After'],
        ], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $created = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsArray($created['operation']);
        self::assertIsString($created['operation']['id']);

        $writer = static::getContainer()->get(FakeClientRowWriter::class);
        self::assertInstanceOf(FakeClientRowWriter::class, $writer);
        $writer->rejection = 'undo_data_conflict';
        $client->jsonRequest('POST', '/api/audit/'.$created['operation']['id'].'/undo', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(409);
        self::assertJsonResponseContains(['operation' => ['status' => 'conflict', 'error' => 'undo_data_conflict']], $client->getResponse()->getContent());

        $writer->rejection = null;
        $client->jsonRequest('POST', '/api/audit/'.$created['operation']['id'].'/undo', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['operation' => ['status' => 'succeeded']], $client->getResponse()->getContent());
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
