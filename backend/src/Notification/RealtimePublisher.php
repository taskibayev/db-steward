<?php

namespace App\Notification;

interface RealtimePublisher
{
    /** @param array<string, mixed> $data */
    public function publish(string $channel, array $data): void;
}
