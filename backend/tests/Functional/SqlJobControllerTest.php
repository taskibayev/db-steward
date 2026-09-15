<?php

namespace App\Tests\Functional;

use App\Connection\CredentialCipher;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use App\Message\ExecuteSqlJob;
use App\Message\ExpireQueryResult;
use App\MessageHandler\ExecuteSqlJobHandler;
use App\MessageHandler\ExpireQueryResultHandler;
use App\Permission\AccessMode;
use App\Repository\JobRepository;
use App\Repository\TemporaryQueryResultRepository;
use App\Tests\DatabaseWebTestCase;
use App\Tests\Support\FakeClientSqlExecutor;
use App\User\UserRole;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class SqlJobControllerTest extends DatabaseWebTestCase
{
    public function testSelectRunsAsynchronouslyAndResultIsPrivate(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('POST', $this->url($connection), ['sql' => 'SELECT id, name FROM customers'], ['HTTP_X_CSRF_TOKEN' => $csrf]);

        self::assertResponseStatusCodeSame(202);
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertSame('queued', $response['job']['status']);
        self::assertIsString($response['job']['id']);
        $this->handle($response['job']['id']);

        $client->request('GET', '/api/jobs/'.$response['job']['id']);
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['job' => [
            'status' => 'succeeded',
            'operation' => 'select',
            'tables' => ['customers'],
            'result' => ['columns' => ['id', 'name'], 'rows' => [['id' => 1, 'name' => 'Northwind']], 'rowCount' => 1, 'truncated' => false],
        ]], $client->getResponse()->getContent());

        $other = new User('other@example.com', UserRole::Manager);
        $entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $entityManager->persist($other);
        $entityManager->flush();
        $client->loginUser($other);
        $client->request('GET', '/api/jobs/'.$response['job']['id']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testWriteRequiresAcknowledgementAndReturnsAffectedRows(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $payload = ['sql' => "UPDATE customers SET name = 'Changed' WHERE id = 1"];
        $client->jsonRequest('POST', $this->url($connection), $payload, ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonResponseContains(['error' => 'write_acknowledgement_required'], $client->getResponse()->getContent());

        $client->jsonRequest('POST', $this->url($connection), [...$payload, 'writeAcknowledged' => true], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(202);
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['job']['id']);
        $this->handle($response['job']['id']);
        $client->request('GET', '/api/jobs/'.$response['job']['id']);
        self::assertJsonResponseContains(['job' => ['status' => 'succeeded', 'affectedRows' => 7, 'result' => null]], $client->getResponse()->getContent());
    }

    public function testQueuedCancellationIsGuaranteedAndDuplicateDeliveryIsIgnored(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('POST', $this->url($connection), ['sql' => 'SELECT * FROM customers'], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['job']['id']);
        $client->jsonRequest('POST', '/api/jobs/'.$response['job']['id'].'/cancel', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertJsonResponseContains(['job' => ['status' => 'cancelled']], $client->getResponse()->getContent());
        $this->handle($response['job']['id']);
        self::assertSame(0, $this->executor()->calls);

        $client->jsonRequest('POST', $this->url($connection), ['sql' => 'SELECT * FROM customers'], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $second = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($second['job']['id']);
        $this->handle($second['job']['id']);
        $this->handle($second['job']['id']);
        self::assertSame(1, $this->executor()->calls);
    }

    public function testWorkerRechecksActiveUserAndPermissions(): void
    {
        [$client, $connection, $manager] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('POST', $this->url($connection), ['sql' => 'DELETE FROM customers', 'writeAcknowledged' => true], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['job']['id']);
        $entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $manager = $entityManager->find(User::class, $manager->getId());
        self::assertInstanceOf(User::class, $manager);
        $manager->setActive(false);
        $entityManager->flush();
        $this->handle($response['job']['id']);

        $job = static::getContainer()->get(JobRepository::class)->find($response['job']['id']);
        self::assertNotNull($job);
        self::assertSame('failed', $job->getStatus()->value);
        self::assertSame('authorization_revoked', $job->getErrorCode());
        self::assertSame(0, $this->executor()->calls);
    }

    public function testExpiredSelectResultIsPhysicallyDeleted(): void
    {
        [$client, $connection] = $this->managerClient(AccessMode::DefaultAllow);
        $csrf = $this->csrf($client);
        $client->jsonRequest('POST', $this->url($connection), ['sql' => 'SELECT * FROM customers'], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['job']['id']);
        $this->handle($response['job']['id']);

        $repository = static::getContainer()->get(TemporaryQueryResultRepository::class);
        $result = $repository->findOneBy([]);
        self::assertNotNull($result);
        $property = new \ReflectionProperty($result, 'expiresAt');
        $property->setValue($result, new \DateTimeImmutable('-1 second'));
        static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->flush();
        static::getContainer()->get(ExpireQueryResultHandler::class)(new ExpireQueryResult($result->getId()->toRfc4122()));

        self::assertSame(0, $repository->count([]));
    }

    /** @return array{KernelBrowser, ClientConnection, User} */
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

        return [$client, $connection, $manager];
    }

    private function handle(string $jobId): void
    {
        static::getContainer()->get(ExecuteSqlJobHandler::class)(new ExecuteSqlJob($jobId));
    }

    private function executor(): FakeClientSqlExecutor
    {
        $executor = static::getContainer()->get(FakeClientSqlExecutor::class);
        self::assertInstanceOf(FakeClientSqlExecutor::class, $executor);

        return $executor;
    }

    private function csrf(KernelBrowser $client): string
    {
        $client->request('GET', '/api/auth/csrf');
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['token']);

        return $response['token'];
    }

    private function url(ClientConnection $connection): string
    {
        return '/api/connections/'.$connection->getId()->toRfc4122().'/sql-jobs';
    }
}
