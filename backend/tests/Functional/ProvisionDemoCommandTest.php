<?php

namespace App\Tests\Functional;

use App\Connection\ConnectionStatus;
use App\Entity\User;
use App\Repository\ClientConnectionRepository;
use App\Repository\UserDatabaseAccessRepository;
use App\Repository\UserRepository;
use App\Tests\DatabaseWebTestCase;
use App\User\UserRole;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ProvisionDemoCommandTest extends DatabaseWebTestCase
{
    public function testProvisioningIsIdempotent(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        self::assertNotNull(self::$kernel);
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:demo:provision'));

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => ' MANAGER@example.com ']));
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'manager@example.com']));

        $manager = static::getContainer()->get(UserRepository::class)->findByEmail('manager@example.com');
        self::assertInstanceOf(User::class, $manager);
        self::assertSame(UserRole::Manager, $manager->getRole());
        $connections = static::getContainer()->get(ClientConnectionRepository::class)->findAll();
        self::assertCount(2, $connections);
        foreach ($connections as $connection) {
            self::assertSame(ConnectionStatus::Reachable, $connection->getStatus());
        }
        self::assertCount(2, static::getContainer()->get(UserDatabaseAccessRepository::class)->findForUser($manager));
    }
}
