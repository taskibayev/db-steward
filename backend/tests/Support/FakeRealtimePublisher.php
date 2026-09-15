<?php

namespace App\Tests\Support;

use App\Notification\RealtimePublisher;

final class FakeRealtimePublisher implements RealtimePublisher
{
    /** @var list<array{channel: string, data: array<string, mixed>}> */
    public array $publications = [];

    public function publish(string $channel, array $data): void
    {
        $this->publications[] = ['channel' => $channel, 'data' => $data];
    }
}
