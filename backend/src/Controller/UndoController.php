<?php

namespace App\Controller;

use App\Audit\AuditStatus;
use App\Audit\AuditView;
use App\Crud\CrudAccessDenied;
use App\Crud\RowMutationRejected;
use App\Entity\AuditOperation;
use App\Entity\User;
use App\Repository\AuditOperationRepository;
use App\Undo\UndoManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class UndoController extends AbstractController
{
    #[Route('/api/audit/{id}/undo', name: 'api_audit_undo', methods: ['POST'])]
    public function undo(string $id, Request $request, AuditOperationRepository $operations, UndoManager $manager): JsonResponse
    {
        $original = Uuid::isValid($id) ? $operations->find($id) : null;
        $actor = $this->getUser();
        if (!$original instanceof AuditOperation || !$actor instanceof User) {
            return $this->json(['error' => 'operation_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $operation = $manager->undo($actor, $original, $this->correlationId($request));
        } catch (CrudAccessDenied) {
            return $this->json(['error' => 'operation_not_allowed'], Response::HTTP_FORBIDDEN);
        } catch (RowMutationRejected $exception) {
            return $this->json(['error' => $exception->errorCode], Response::HTTP_CONFLICT);
        }

        if (AuditStatus::Failed === $operation->getStatus()) {
            return $this->json(['error' => 'client_write_failed', 'operation' => AuditView::fromEntity($operation)], Response::HTTP_BAD_GATEWAY);
        }
        $status = AuditStatus::Succeeded === $operation->getStatus() ? Response::HTTP_OK : Response::HTTP_CONFLICT;

        return $this->json(['operation' => AuditView::fromEntity($operation)], $status);
    }

    private function correlationId(Request $request): string
    {
        $id = $request->attributes->get('_correlation_id');

        return is_string($id) ? $id : Uuid::v7()->toRfc4122();
    }
}
