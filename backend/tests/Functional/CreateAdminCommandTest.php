<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\DatabaseWebTestCase;
use App\User\UserRole;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAdminCommandTest extends DatabaseWebTestCase
{
    public function testCreatesNormalizedAdministratorAndRejectsDuplicate(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        self::assertNotNull(self::$kernel);
        $application = new Application(self::$kernel);
        $command = $application->find('app:user:create-admin');
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => ' ADMIN@Example.com ']));

        $user = static::getContainer()->get(UserRepository::class)->findByEmail('admin@example.com');
        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserRole::Administrator, $user->getRole());
        self::assertTrue($user->isActive());

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'admin@example.com']));
    }
}
