<?php

namespace App\Notification;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RealtimeTokenFactory
{
    public function __construct(#[Autowire(env: 'CENTRIFUGO_TOKEN_SECRET')] private readonly string $secret)
    {
    }

    /** @return array{token: string, expiresAt: string} */
    public function create(User $user): array
    {
        $expiresAt = new \DateTimeImmutable('+5 minutes');
        $header = $this->encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $this->encode([
            'sub' => $user->getId()->toRfc4122(),
            'exp' => $expiresAt->getTimestamp(),
            'channels' => [NotificationManager::channel($user)],
        ]);
        $signature = self::base64Url(hash_hmac('sha256', $header.'.'.$payload, $this->secret, true));

        return ['token' => $header.'.'.$payload.'.'.$signature, 'expiresAt' => $expiresAt->format(DATE_ATOM)];
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return self::base64Url(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
