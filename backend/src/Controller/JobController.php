<?php

namespace App\Controller;

use App\Dto\AuditHistoryQuery;
use App\Dto\CreateSqlJobRequest;
use App\Entity\ClientConnection;
use App\Entity\Job;
use App\Entity\User;
use App\Job\JobAccessDenied;
use App\Job\JobView;
use App\Job\SqlJobManager;
use App\Repository\ClientConnectionRepository;
use App\Repository\JobRepository;
use App\Repository\TemporaryQueryResultRepository;
use App\Sql\SqlRejected;
use App\User\UserRole;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class JobController extends AbstractController
{
    #[Route('/api/connections/{id}/sql-jobs', name: 'api_sql_job_create', methods: ['POST'])]
    public function create(
        string $id,
        #[MapRequestPayload] CreateSqlJobRequest $payload,
        Request $request,
        ClientConnectionRepository $connections,
        SqlJobManager $manager,
    ): JsonResponse {
        $connection = Uuid::isValid($id) ? $connections->find($id) : null;
        $actor = $this->getUser();
        if (!$connection instanceof ClientConnection || !$actor instanceof User) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }
        try {
            $job = $manager->enqueue($actor, $connection, $payload->sql, $payload->writeAcknowledged, $this->correlationId($request));
        } catch (JobAccessDenied) {
            return $this->json(['error' => 'operation_not_allowed'], Response::HTTP_FORBIDDEN);
        } catch (SqlRejected $exception) {
            return $this->json(['error' => $exception->errorCode], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['job' => JobView::fromEntity($job)], Response::HTTP_ACCEPTED);
    }

    #[Route('/api/jobs', name: 'api_jobs_list', methods: ['GET'])]
    public function list(JobRepository $jobs, #[MapQueryString] AuditHistoryQuery $query = new AuditHistoryQuery()): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->json(['error' => 'authentication_required'], Response::HTTP_UNAUTHORIZED);
        }
        $all = UserRole::Administrator === $actor->getRole();
        $total = $jobs->countVisible($actor, $all);

        return $this->json([
            'items' => array_map(static fn (Job $job): array => JobView::fromEntity($job), $jobs->findPage($actor, $all, $query->page, $query->pageSize)),
            'pagination' => ['page' => $query->page, 'pageSize' => $query->pageSize, 'total' => $total, 'pages' => max(1, (int) ceil($total / $query->pageSize))],
        ]);
    }

    #[Route('/api/jobs/{id}', name: 'api_jobs_show', methods: ['GET'])]
    public function show(string $id, JobRepository $jobs, TemporaryQueryResultRepository $results): JsonResponse
    {
        $job = Uuid::isValid($id) ? $jobs->find($id) : null;
        $actor = $this->getUser();
        if (!$job instanceof Job || !$actor instanceof User || !$this->canView($actor, $job)) {
            return $this->json(['error' => 'job_not_found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['job' => JobView::fromEntity($job, $results->findActiveForJob($job))]);
    }

    #[Route('/api/jobs/{id}/cancel', name: 'api_jobs_cancel', methods: ['POST'])]
    public function cancel(string $id, JobRepository $jobs, EntityManagerInterface $entityManager): JsonResponse
    {
        $actor = $this->getUser();
        if (!Uuid::isValid($id) || !$actor instanceof User) {
            return $this->json(['error' => 'job_not_found'], Response::HTTP_NOT_FOUND);
        }
        $job = $entityManager->wrapInTransaction(function () use ($id, $actor, $jobs, $entityManager): ?Job {
            $job = $jobs->find($id);
            if (!$job instanceof Job || !$this->canView($actor, $job)) {
                return null;
            }
            $entityManager->lock($job, LockMode::PESSIMISTIC_WRITE);
            $job->requestCancellation();
            $entityManager->flush();

            return $job;
        });
        if (!$job instanceof Job) {
            return $this->json(['error' => 'job_not_found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['job' => JobView::fromEntity($job)]);
    }

    private function canView(User $actor, Job $job): bool
    {
        return UserRole::Administrator === $actor->getRole() || $actor->getId()->equals($job->getActor()->getId());
    }

    private function correlationId(Request $request): string
    {
        $id = $request->attributes->get('_correlation_id');

        return is_string($id) ? $id : Uuid::v7()->toRfc4122();
    }
}
