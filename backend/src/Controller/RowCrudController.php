<?php

namespace App\Controller;

use App\Audit\AuditView;
use App\Crud\CrudAccessDenied;
use App\Crud\CrudManager;
use App\Crud\RowMutationFailed;
use App\Crud\RowMutationRejected;
use App\Dto\DeleteRowRequest;
use App\Dto\InsertRowRequest;
use App\Dto\UpdateRowRequest;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Repository\ClientConnectionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/connections/{id}/tables/{table}/rows', requirements: ['table' => '[^/]+'])]
final class RowCrudController extends AbstractController
{
    #[Route('', name: 'api_row_insert', methods: ['POST'])]
    public function insert(
        string $id,
        string $table,
        #[MapRequestPayload]
        InsertRowRequest $payload,
        Request $request,
        ClientConnectionRepository $connections,
        CrudManager $manager,
    ): JsonResponse {
        return $this->execute($id, $connections, fn (User $user, ClientConnection $connection): JsonResponse => $this->json(
            ['operation' => AuditView::fromEntity($manager->insert($user, $connection, $table, $payload->values, $this->correlationId($request)))],
            Response::HTTP_CREATED,
        ));
    }

    #[Route('/one', name: 'api_row_update', methods: ['PUT'])]
    public function update(
        string $id,
        string $table,
        #[MapRequestPayload]
        UpdateRowRequest $payload,
        Request $request,
        ClientConnectionRepository $connections,
        CrudManager $manager,
    ): JsonResponse {
        return $this->execute($id, $connections, fn (User $user, ClientConnection $connection): JsonResponse => $this->json([
            'operation' => AuditView::fromEntity($manager->update($user, $connection, $table, $payload->primaryKey, $payload->values, $this->correlationId($request))),
        ]));
    }

    #[Route('/one', name: 'api_row_delete', methods: ['DELETE'])]
    public function delete(
        string $id,
        string $table,
        #[MapRequestPayload]
        DeleteRowRequest $payload,
        Request $request,
        ClientConnectionRepository $connections,
        CrudManager $manager,
    ): JsonResponse {
        return $this->execute($id, $connections, fn (User $user, ClientConnection $connection): JsonResponse => $this->json([
            'operation' => AuditView::fromEntity($manager->delete($user, $connection, $table, $payload->primaryKey, $this->correlationId($request))),
        ]));
    }

    /** @param callable(User, ClientConnection): JsonResponse $operation */
    private function execute(string $id, ClientConnectionRepository $connections, callable $operation): JsonResponse
    {
        $connection = Uuid::isValid($id) ? $connections->find($id) : null;
        $user = $this->getUser();
        if (!$connection instanceof ClientConnection || !$user instanceof User) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            return $operation($user, $connection);
        } catch (CrudAccessDenied) {
            return $this->json(['error' => 'operation_not_allowed'], Response::HTTP_FORBIDDEN);
        } catch (RowMutationRejected $exception) {
            return $this->json(['error' => $exception->errorCode], Response::HTTP_CONFLICT);
        } catch (RowMutationFailed) {
            return $this->json(['error' => 'client_write_failed'], Response::HTTP_BAD_GATEWAY);
        }
    }

    private function correlationId(Request $request): string
    {
        $id = $request->attributes->get('_correlation_id');

        return is_string($id) ? $id : Uuid::v7()->toRfc4122();
    }
}
