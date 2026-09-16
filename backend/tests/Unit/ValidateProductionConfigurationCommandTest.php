<?php

namespace App\Tests\Unit;

use App\Command\ValidateProductionConfigurationCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ValidateProductionConfigurationCommandTest extends TestCase
{
    public function testAcceptsStrongProductionConfigurationWithoutPrintingSecrets(): void
    {
        $secret = str_repeat('s', 32);
        $tester = new CommandTester(new ValidateProductionConfigurationCommand(
            'prod', $secret, 'mysql://database', 'amqps://queue', 'google-client', $secret,
            base64_encode(str_repeat('k', 32)), $secret, $secret,
        ));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Production configuration is valid.', $tester->getDisplay());
        self::assertStringNotContainsString($secret, $tester->getDisplay());
    }

    public function testRejectsDevelopmentAndPlaceholderValues(): void
    {
        $tester = new CommandTester(new ValidateProductionConfigurationCommand(
            'dev', 'local-development-only', 'sqlite://database', 'sync://', 'replace-me',
            'replace-me', 'invalid', 'replace-me', 'replace-me',
        ));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('APP_ENV must be prod.', $tester->getDisplay());
        self::assertStringContainsString('CLIENT_CREDENTIALS_KEY', $tester->getDisplay());
    }
}
