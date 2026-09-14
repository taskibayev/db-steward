<?php

namespace App\Tests\Functional;

use App\Connection\ConnectionTestResult;
use App\Connection\CredentialCipher;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Repository\ClientConnectionRepository;
use App\Tests\DatabaseWebTestCase;
use App\Tests\Support\FakeClientDatabaseConnector;
use App\User\UserRole;

final class AdminConnectionControllerTest extends DatabaseWebTestCase
{
    public function testAdministratorCreatesConnectionWithoutExposingCredentials(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);
        $fakeConnector = static::getContainer()->get(FakeClientDatabaseConnector::class);
        self::assertInstanceOf(FakeClientDatabaseConnector::class, $fakeConnector);

        $client->request('GET', '/api/auth/csrf');
        /** @var array{token: string} $csrf */
        $csrf = self::decodeJsonResponse($client->getResponse()->getContent());
        $client->jsonRequest('POST', '/api/admin/connections', [
            'name' => 'Production reporting',
            'host' => 'mysql.example.internal',
            'port' => 3306,
            'database' => 'client_app',
            'username' => 'readonly_user',
            'password' => 'top-secret-password',
        ], ['HTTP_X_CSRF_TOKEN' => $csrf['token']]);

        self::assertResponseStatusCodeSame(201);
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('readonly_user', $content);
        self::assertStringNotContainsString('top-secret-password', $content);
        self::assertJsonResponseContains([
            'connection' => [
                'name' => 'Production reporting',
                'database' => 'client_app',
                'status' => 'reachable',
                'serverVersion' => '8.4.7',
                'credentialsConfigured' => true,
            ],
        ], $content);

        $stored = static::getContainer()->get(ClientConnectionRepository::class)->findOneBy([]);
        self::assertInstanceOf(ClientConnection::class, $stored);
        self::assertNotSame('readonly_user', $stored->getEncryptedUsername());
        self::assertNotSame('top-secret-password', $stored->getEncryptedPassword());
        $cipher = static::getContainer()->get(CredentialCipher::class);
        self::assertSame('readonly_user', $cipher->decrypt($stored->getEncryptedUsername()));
        self::assertSame('top-secret-password', $cipher->decrypt($stored->getEncryptedPassword()));
        self::assertSame('readonly_user', $fakeConnector->lastCredentials?->username);
    }

    public function testFailedRetestReturnsSafeErrorAndStoresStatus(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $cipher = static::getContainer()->get(CredentialCipher::class);
        $connection = new ClientConnection(
            'Client',
            'db.example.com',
            3306,
            'client',
            $cipher->encrypt('client_user'),
            $cipher->encrypt('secret'),
        );
        $entityManager->persist($admin);
        $entityManager->persist($connection);
        $entityManager->flush();
        $client->loginUser($admin);
        $fakeConnector = static::getContainer()->get(FakeClientDatabaseConnector::class);
        self::assertInstanceOf(FakeClientDatabaseConnector::class, $fakeConnector);
        $fakeConnector->setResult(ConnectionTestResult::failure('connection_failed'));

        $client->request('GET', '/api/auth/csrf');
        /** @var array{token: string} $csrf */
        $csrf = self::decodeJsonResponse($client->getResponse()->getContent());
        $client->request('POST', '/api/admin/connections/'.$connection->getId()->toRfc4122().'/test', server: [
            'HTTP_X_CSRF_TOKEN' => $csrf['token'],
        ]);

        self::assertResponseStatusCodeSame(502);
        self::assertJsonResponseContains([
            'connection' => ['status' => 'unreachable', 'lastErrorCode' => 'connection_failed'],
        ], $client->getResponse()->getContent());
    }

    public function testManagerCannotManageConnections(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $manager = new User('manager@example.com', UserRole::Manager);
        $entityManager->persist($manager);
        $entityManager->flush();
        $client->loginUser($manager);

        $client->request('GET', '/api/admin/connections');
        self::assertResponseStatusCodeSame(403);
    }
}
