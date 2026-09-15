<?php

namespace App\Controller;

use App\Audit\AuditView;
use App\Dto\AuditHistoryQuery;
use App\Entity\AuditOperation;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Permission\PermissionChecker;
use App\Repository\AuditOperationRepository;
use App\Repository\ClientConnectionRepository;
use App\Undo\UndoManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class AuditController extends AbstractController
{
    #[Route('/api/connections/{id}/history', name: 'api_audit_history', methods: ['GET'])]
    public function list(
        string $id,
        ClientConnectionRepository $connections,
        AuditOperationRepository $operations,
        PermissionChecker $permissions,
        UndoManager $undo,
        #[MapQueryString]
        AuditHistoryQuery $query = new AuditHistoryQuery(),
    ): JsonResponse {
        $connection = Uuid::isValid($id) ? $connections->find($id) : null;
        $user = $this->getUser();
        if (!$connection instanceof ClientConnection || !$user instanceof User) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }
        if (!$permissions->canAccessDatabase($user, $connection)) {
            return $this->json(['error' => 'database_access_denied'], Response::HTTP_FORBIDDEN);
        }

        $total = $operations->countForConnection($connection);

        return $this->json([
            'items' => array_map(
                fn (AuditOperation $operation): array => AuditView::fromEntity($operation, $undo->canUndo($user, $operation)),
                $operations->findPage($connection, $query->page, $query->pageSize),
            ),
            'pagination' => [
                'page' => $query->page,
                'pageSize' => $query->pageSize,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $query->pageSize)),
            ],
        ]);
    }
}
