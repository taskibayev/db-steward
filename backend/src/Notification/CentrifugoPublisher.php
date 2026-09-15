<?php

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CentrifugoPublisher implements RealtimePublisher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'CENTRIFUGO_API_URL')] private readonly string $apiUrl,
        #[Autowire(env: 'CENTRIFUGO_API_KEY')] private readonly string $apiKey,
    ) {
    }

    public function publish(string $channel, array $data): void
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl.'/api/publish', [
                'headers' => ['Authorization' => 'apikey '.$this->apiKey],
                'json' => ['channel' => $channel, 'data' => $data],
                'timeout' => 3,
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException('Centrifugo rejected publication.');
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Realtime notification delivery failed.', [
                'code' => 'realtime_publish_failed',
                'exceptionClass' => $exception::class,
            ]);
        }
    }
}
