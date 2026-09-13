<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\DatabaseWebTestCase;
use App\User\UserRole;

final class AdminUserControllerTest extends DatabaseWebTestCase
{
    public function testAdministratorCreatesAndDisablesManagerWithCsrfProtection(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $client->jsonRequest('POST', '/api/admin/users', ['email' => 'manager@example.com']);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/api/auth/csrf');
        /** @var array{token: string} $csrf */
        $csrf = self::decodeJsonResponse($client->getResponse()->getContent());

        $client->jsonRequest('POST', '/api/admin/users', ['email' => 'Manager@Example.com'], [
            'HTTP_X_CSRF_TOKEN' => $csrf['token'],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonResponseContains(
            ['user' => ['email' => 'manager@example.com', 'role' => 'manager']],
            $client->getResponse()->getContent(),
        );

        /** @var array{user: array{id: string}} $created */
        $created = self::decodeJsonResponse($client->getResponse()->getContent());
        $client->jsonRequest('PATCH', '/api/admin/users/'.$created['user']['id'].'/status', ['active' => false], [
            'HTTP_X_CSRF_TOKEN' => $csrf['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['user' => ['active' => false]], $client->getResponse()->getContent());
    }

    public function testManagerCannotUseAdministrationApi(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $manager = new User('manager@example.com', UserRole::Manager);
        $entityManager->persist($manager);
        $entityManager->flush();
        $client->loginUser($manager);

        $client->request('GET', '/api/admin/users');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCannotDisableOwnAccount(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $admin = new User('admin@example.com', UserRole::Administrator);
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $client->request('GET', '/api/auth/csrf');
        /** @var array{token: string} $csrf */
        $csrf = self::decodeJsonResponse($client->getResponse()->getContent());
        $client->jsonRequest('PATCH', '/api/admin/users/'.$admin->getId()->toRfc4122().'/status', ['active' => false], [
            'HTTP_X_CSRF_TOKEN' => $csrf['token'],
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertJsonResponseContains(['error' => 'cannot_disable_self'], $client->getResponse()->getContent());
    }
}
