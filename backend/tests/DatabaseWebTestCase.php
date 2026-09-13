<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseWebTestCase extends WebTestCase
{
    /** @param array<string, mixed> $expected */
    protected static function assertJsonResponseContains(array $expected, string|false $content): void
    {
        self::assertIsString($content);
        $actual = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($actual);
        self::assertArrayContains($expected, $actual);
    }

    /** @return array<string, mixed> */
    protected static function decodeJsonResponse(string|false $content): array
    {
        self::assertIsString($content);
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private static function assertArrayContains(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $actual);
            if (is_array($value)) {
                self::assertIsArray($actual[$key]);
                self::assertArrayContains($value, $actual[$key]);
            } else {
                self::assertSame($value, $actual[$key]);
            }
        }
    }

    protected function resetDatabase(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $entityManager;
    }
}
