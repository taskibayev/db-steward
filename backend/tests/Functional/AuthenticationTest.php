<?php

namespace App\Tests\Functional;

use App\Auth\MockOAuthProvider;
use App\Entity\User;
use App\Tests\DatabaseWebTestCase;
use App\User\UserRole;

final class AuthenticationTest extends DatabaseWebTestCase
{
    public function testKnownActiveUserCanLoginThroughMockProvider(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $entityManager->persist(new User('admin@example.com', UserRole::Administrator));
        $entityManager->flush();

        $client->request('GET', '/api/auth/oauth/mock/start?email=admin@example.com');
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseRedirects('/');

        $client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(
            [
                'authenticated' => true,
                'user' => ['email' => 'admin@example.com', 'role' => 'administrator', 'active' => true],
            ],
            $client->getResponse()->getContent(),
        );
    }

    public function testUnknownAndDisabledUsersAreRejected(): void
    {
        $client = static::createClient();
        $entityManager = $this->resetDatabase();
        $disabled = new User('disabled@example.com', UserRole::Manager);
        $disabled->setActive(false);
        $entityManager->persist($disabled);
        $entityManager->flush();

        $client->request('GET', '/api/auth/oauth/mock/start?email=unknown@example.com');
        $client->followRedirect();
        self::assertResponseRedirects('/?auth_error=access_denied');

        $client->request('GET', '/api/auth/oauth/mock/start?email=disabled@example.com');
        $client->followRedirect();
        self::assertResponseRedirects('/?auth_error=access_denied');

        $client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['authenticated' => false], $client->getResponse()->getContent());
    }

    public function testMockProviderCannotBeConstructedForProduction(): void
    {
        $this->expectException(\LogicException::class);
        new MockOAuthProvider('prod');
    }
}
