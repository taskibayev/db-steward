<?php

namespace App\Notification;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RealtimePublisher $publisher,
    ) {
    }

    /** @param array<string, bool|float|int|string|null> $data */
    public function create(User $recipient, string $type, array $data): Notification
    {
        $notification = new Notification($recipient, $type, $data);
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        $this->publisher->publish(self::channel($recipient), [
            'notificationId' => $notification->getId()->toRfc4122(),
            'type' => $type,
            'createdAt' => $notification->getCreatedAt()->format(DATE_ATOM),
        ]);

        return $notification;
    }

    public static function channel(User $user): string
    {
        return 'user:'.$user->getId()->toRfc4122();
    }
}
