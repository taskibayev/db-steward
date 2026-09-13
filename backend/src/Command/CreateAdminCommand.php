<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\User\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create-admin', description: 'Create a DB Steward administrator')]
final class CreateAdminCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Administrator email address')]
        string $email,
        SymfonyStyle $io,
    ): int {
        $email = User::normalizeEmail($email);
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('A valid email address is required.');

            return Command::INVALID;
        }

        if (null !== $this->users->findByEmail($email)) {
            $io->error('A user with this email already exists.');

            return Command::FAILURE;
        }

        $user = new User($email, UserRole::Administrator);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Administrator %s created.', $email));

        return Command::SUCCESS;
    }
}
