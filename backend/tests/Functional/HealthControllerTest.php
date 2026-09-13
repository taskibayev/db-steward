<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testLivenessEndpointIsAvailable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('X-Correlation-ID');
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $client->getResponse()->getContent());
    }
}
