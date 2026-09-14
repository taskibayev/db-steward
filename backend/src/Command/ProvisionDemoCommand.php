<?php

namespace App\Command;

use App\Connection\ConnectionManager;
use App\Connection\CredentialCipher;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use App\Permission\AccessMode;
use App\Repository\ClientConnectionRepository;
use App\Repository\UserDatabaseAccessRepository;
use App\Repository\UserRepository;
use App\User\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'app:demo:provision', description: 'Provision the local demo manager and client database connections')]
final class ProvisionDemoCommand
{
    private const CONNECTIONS = [
        'Northwind Demo' => 'northwind',
        'Sakila Demo' => 'sakila',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly ClientConnectionRepository $connections,
        private readonly UserDatabaseAccessRepository $accesses,
        private readonly CredentialCipher $cipher,
        private readonly ConnectionManager $connectionManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Demo manager email address')]
        string $email,
        SymfonyStyle $io,
    ): int {
        if ('prod' === $this->kernel->getEnvironment()) {
            $io->error('Demo provisioning is disabled in production.');

            return Command::FAILURE;
        }

        $email = User::normalizeEmail($email);
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('A valid email address is required.');

            return Command::INVALID;
        }

        $user = $this->users->findByEmail($email);
        if (null === $user) {
            $user = new User($email, UserRole::Manager);
            $this->entityManager->persist($user);
        } elseif (UserRole::Manager !== $user->getRole()) {
            $io->error('The requested email belongs to an administrator.');

            return Command::FAILURE;
        }

        foreach (self::CONNECTIONS as $name => $database) {
            $connection = $this->connections->findOneBy(['name' => $name]);
            if (null === $connection) {
                $connection = new ClientConnection(
                    $name,
                    'client-mysql-demo',
                    3306,
                    $database,
                    $this->cipher->encrypt('db_steward_demo'),
                    $this->cipher->encrypt('demo_client_dev'),
                );
                $this->entityManager->persist($connection);
            }

            $this->connectionManager->test($connection);

            if (null === $this->accesses->findAssignment($user, $connection)) {
                $this->entityManager->persist(new UserDatabaseAccess($user, $connection, AccessMode::DefaultAllow));
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('Demo manager %s and two database assignments are ready.', $email));

        return Command::SUCCESS;
    }
}
