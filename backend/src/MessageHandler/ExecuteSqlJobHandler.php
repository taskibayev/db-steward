<?php

namespace App\MessageHandler;

use App\Connection\ClientDatabaseCredentials;
use App\Connection\CredentialCipher;
use App\Entity\Job;
use App\Entity\TemporaryQueryResult;
use App\Job\JobAccessDenied;
use App\Job\SqlJobManager;
use App\Message\ExecuteSqlJob;
use App\Message\ExpireQueryResult;
use App\Repository\JobRepository;
use App\Sql\ClientSqlExecutor;
use App\Sql\SqlOperation;
use App\Sql\SqlRejected;
use App\Sql\SqlValidator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class ExecuteSqlJobHandler
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly SqlValidator $validator,
        private readonly SqlJobManager $manager,
        private readonly ClientSqlExecutor $executor,
        private readonly CredentialCipher $cipher,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExecuteSqlJob $message): void
    {
        $job = $this->entityManager->wrapInTransaction(function () use ($message): ?Job {
            $job = $this->jobs->find($message->jobId);
            if (!$job instanceof Job) {
                return null;
            }
            $this->entityManager->lock($job, LockMode::PESSIMISTIC_WRITE);
            if (!$job->start()) {
                return null;
            }
            $this->entityManager->flush();

            return $job;
        });
        if (!$job instanceof Job) {
            return;
        }

        $execution = $job->getSqlExecution();
        if (null === $execution) {
            $this->fail($job, 'sql_execution_missing');

            return;
        }
        try {
            if (!$job->getActor()->isActive() || !$job->getConnection()->isActive()) {
                throw new JobAccessDenied();
            }
            $parsed = $this->validator->validate($execution->getSqlText(), $job->getConnection()->getDatabaseName());
            if ($parsed->operation !== $execution->getOperation() || $parsed->allTables() !== $execution->getTables()) {
                throw new SqlRejected('sql_validation_changed');
            }
            $this->manager->assertPermissions($job->getActor(), $job->getConnection(), $parsed);
            $result = $this->executor->execute($this->credentials($job), $parsed);
            if (SqlOperation::Select === $parsed->operation) {
                $temporaryResult = new TemporaryQueryResult($job, $result->columns, $result->rows, $result->truncated);
                $this->entityManager->persist($temporaryResult);
            } else {
                $execution->recordAffectedRows($result->affectedRows ?? 0);
            }
            $this->entityManager->flush();
            $job->succeed();
            $this->entityManager->flush();
        } catch (JobAccessDenied) {
            $this->fail($job, 'authorization_revoked');
        } catch (SqlRejected $exception) {
            $this->fail($job, $exception->errorCode);
        } catch (\Throwable $exception) {
            $this->logger->warning('SQL job execution or result persistence failed.', ['code' => 'client_sql_failed', 'exceptionClass' => $exception::class]);
            $this->fail($job, 'client_sql_failed');

            return;
        }

        if (isset($temporaryResult)) {
            try {
                $this->bus->dispatch(new ExpireQueryResult($temporaryResult->getId()->toRfc4122()), [new DelayStamp(3_600_000)]);
            } catch (\Throwable $exception) {
                $this->logger->warning('Could not schedule query-result cleanup.', ['code' => 'query_result_cleanup_dispatch_failed', 'exceptionClass' => $exception::class]);
            }
        }
    }

    private function fail(Job $job, string $errorCode): void
    {
        $job->fail($errorCode);
        $this->entityManager->flush();
    }

    private function credentials(Job $job): ClientDatabaseCredentials
    {
        $connection = $job->getConnection();

        return new ClientDatabaseCredentials($connection->getHost(), $connection->getPort(), $connection->getDatabaseName(), $this->cipher->decrypt($connection->getEncryptedUsername()), $this->cipher->decrypt($connection->getEncryptedPassword()));
    }
}
