<?php

namespace App\Tests\Functional;

use App\Entity\Notification;
use App\Entity\User;
use App\Tests\DatabaseWebTestCase;
use App\Tests\Support\FakeRealtimePublisher;
use App\User\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class NotificationControllerTest extends DatabaseWebTestCase
{
    public function testNotificationsArePrivateAndSupportUnreadState(): void
    {
        [$client, $user, $other] = $this->clientWithUsers();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $own = new Notification($user, 'job_succeeded', ['jobId' => 'job-1']);
        $foreign = new Notification($other, 'job_failed', ['jobId' => 'job-2']);
        $entityManager->persist($own);
        $entityManager->persist($foreign);
        $entityManager->flush();

        $client->request('GET', '/api/notifications');
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains([
            'unreadCount' => 1,
            'pagination' => ['total' => 1],
            'items' => [['id' => $own->getId()->toRfc4122(), 'type' => 'job_succeeded', 'readAt' => null]],
        ], $client->getResponse()->getContent());

        $csrf = $this->csrf($client);
        $client->jsonRequest('POST', '/api/notifications/'.$foreign->getId()->toRfc4122().'/read', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('POST', '/api/notifications/'.$own->getId()->toRfc4122().'/read', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful();
        self::assertJsonResponseContains(['notification' => ['id' => $own->getId()->toRfc4122()]], $client->getResponse()->getContent());

        $client->request('GET', '/api/notifications');
        self::assertJsonResponseContains(['unreadCount' => 0], $client->getResponse()->getContent());
    }

    public function testMarkAllReadAndRealtimeTokenAreUserScoped(): void
    {
        [$client, $user] = $this->clientWithUsers();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Notification($user, 'job_succeeded', []));
        $entityManager->persist(new Notification($user, 'job_failed', []));
        $entityManager->flush();
        $csrf = $this->csrf($client);

        $client->jsonRequest('POST', '/api/notifications/read-all', [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertJsonResponseContains(['updated' => 2], $client->getResponse()->getContent());
        $client->request('GET', '/api/realtime/token');
        self::assertResponseIsSuccessful();
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['token']);
        $parts = explode('.', $response['token']);
        self::assertCount(3, $parts);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($user->getId()->toRfc4122(), $payload['sub']);
        self::assertSame(['user:'.$user->getId()->toRfc4122()], $payload['channels']);
    }

    public function testNotificationManagerPersistsBeforePublishingSafeMetadata(): void
    {
        [, $user] = $this->clientWithUsers();
        $manager = static::getContainer()->get(\App\Notification\NotificationManager::class);
        $notification = $manager->create($user, 'job_succeeded', ['jobId' => 'job-1']);
        /** @var FakeRealtimePublisher $publisher */
        $publisher = static::getContainer()->get(FakeRealtimePublisher::class);

        self::assertCount(1, $publisher->publications);
        self::assertSame('user:'.$user->getId()->toRfc4122(), $publisher->publications[0]['channel']);
        self::assertSame([
            'notificationId' => $notification->getId()->toRfc4122(),
            'type' => 'job_succeeded',
            'createdAt' => $notification->getCreatedAt()->format(DATE_ATOM),
        ], $publisher->publications[0]['data']);
    }

    /** @return array{KernelBrowser, User, User} */
    private function clientWithUsers(): array
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = $this->resetDatabase();
        $user = new User('manager@example.com', UserRole::Manager);
        $other = new User('other@example.com', UserRole::Manager);
        $entityManager->persist($user);
        $entityManager->persist($other);
        $entityManager->flush();
        $client->loginUser($user);

        return [$client, $user, $other];
    }

    private function csrf(KernelBrowser $client): string
    {
        $client->request('GET', '/api/auth/csrf');
        $response = self::decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsString($response['token']);

        return $response['token'];
    }
}
