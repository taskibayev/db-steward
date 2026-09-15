<?php

namespace App\Notification;

use App\Entity\Notification;

final class NotificationView
{
    /** @return array<string, mixed> */
    public static function fromEntity(Notification $notification): array
    {
        return [
            'id' => $notification->getId()->toRfc4122(),
            'type' => $notification->getType(),
            'data' => $notification->getData(),
            'createdAt' => $notification->getCreatedAt()->format(DATE_ATOM),
            'readAt' => $notification->getReadAt()?->format(DATE_ATOM),
        ];
    }
}
