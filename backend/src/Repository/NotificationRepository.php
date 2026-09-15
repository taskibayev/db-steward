<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
final class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return list<Notification> */
    public function findPage(User $recipient, int $page, int $pageSize): array
    {
        return $this->findBy(['recipient' => $recipient], ['createdAt' => 'DESC'], $pageSize, ($page - 1) * $pageSize);
    }

    public function countFor(User $recipient): int
    {
        return $this->count(['recipient' => $recipient]);
    }

    public function countUnread(User $recipient): int
    {
        return $this->count(['recipient' => $recipient, 'readAt' => null]);
    }

    public function markAllRead(User $recipient): int
    {
        $unread = $this->findBy(['recipient' => $recipient, 'readAt' => null]);
        foreach ($unread as $notification) {
            $notification->markRead();
        }

        return count($unread);
    }
}
