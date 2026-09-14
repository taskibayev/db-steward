<?php

namespace App\Controller;

use App\Dto\CreateDatabaseAccessRequest;
use App\Dto\UpdateDatabaseAccessRequest;
use App\Dto\UpdateTablePermissionRequest;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use App\Entity\UserTablePermission;
use App\Permission\AccessMode;
use App\Permission\AccessView;
use App\Repository\ClientConnectionRepository;
use App\Repository\UserDatabaseAccessRepository;
use App\Repository\UserRepository;
use App\Repository\UserTablePermissionRepository;
use App\User\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin/access')]
final class AdminAccessController extends AbstractController
{
    #[Route('', name: 'api_admin_access_list', methods: ['GET'])]
    public function list(UserDatabaseAccessRepository $accesses): JsonResponse
    {
        return $this->json(['items' => array_map(AccessView::fromEntity(...), $accesses->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_access_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreateDatabaseAccessRequest $request,
        UserRepository $users,
        ClientConnectionRepository $connections,
        UserDatabaseAccessRepository $accesses,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $users->find($request->userId);
        $connection = $connections->find($request->connectionId);
        if (!$user instanceof User || UserRole::Manager !== $user->getRole()) {
            return $this->json(['error' => 'manager_not_found'], Response::HTTP_NOT_FOUND);
        }
        if (!$connection instanceof ClientConnection) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }
        if (null !== $accesses->findAssignment($user, $connection)) {
            return $this->json(['error' => 'access_already_exists'], Response::HTTP_CONFLICT);
        }

        $access = new UserDatabaseAccess($user, $connection, AccessMode::from($request->mode));
        $entityManager->persist($access);
        $entityManager->flush();

        return $this->json(['access' => AccessView::fromEntity($access)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_access_update', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload]
        UpdateDatabaseAccessRequest $request,
        UserDatabaseAccessRepository $accesses,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $access = $this->findAccess($id, $accesses);
        if (!$access instanceof UserDatabaseAccess) {
            return $this->json(['error' => 'access_not_found'], Response::HTTP_NOT_FOUND);
        }
        $access->setMode(AccessMode::from($request->mode));
        $entityManager->flush();

        return $this->json(['access' => AccessView::fromEntity($access)]);
    }

    #[Route('/{id}', name: 'api_admin_access_delete', methods: ['DELETE'])]
    public function delete(string $id, UserDatabaseAccessRepository $accesses, EntityManagerInterface $entityManager): Response
    {
        $access = $this->findAccess($id, $accesses);
        if (!$access instanceof UserDatabaseAccess) {
            return $this->json(['error' => 'access_not_found'], Response::HTTP_NOT_FOUND);
        }
        $entityManager->remove($access);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/tables/{table}', name: 'api_admin_access_table_update', methods: ['PUT'], requirements: ['table' => '[A-Za-z0-9_$-]{1,64}'])]
    public function updateTable(
        string $id,
        string $table,
        #[MapRequestPayload]
        UpdateTablePermissionRequest $request,
        UserDatabaseAccessRepository $accesses,
        UserTablePermissionRepository $tablePermissions,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $access = $this->findAccess($id, $accesses);
        if (!$access instanceof UserDatabaseAccess) {
            return $this->json(['error' => 'access_not_found'], Response::HTTP_NOT_FOUND);
        }

        $permission = $tablePermissions->findRule($access, $table) ?? new UserTablePermission($access, $table);
        $permission->update($request->select, $request->insert, $request->update, $request->delete);
        $entityManager->persist($permission);
        $entityManager->flush();

        return $this->json(['tablePermission' => AccessView::tablePermission($permission)]);
    }

    #[Route('/{id}/tables/{table}', name: 'api_admin_access_table_delete', methods: ['DELETE'], requirements: ['table' => '[A-Za-z0-9_$-]{1,64}'])]
    public function deleteTable(
        string $id,
        string $table,
        UserDatabaseAccessRepository $accesses,
        UserTablePermissionRepository $tablePermissions,
        EntityManagerInterface $entityManager,
    ): Response {
        $access = $this->findAccess($id, $accesses);
        if (!$access instanceof UserDatabaseAccess) {
            return $this->json(['error' => 'access_not_found'], Response::HTTP_NOT_FOUND);
        }
        $permission = $tablePermissions->findRule($access, $table);
        if ($permission instanceof UserTablePermission) {
            $entityManager->remove($permission);
            $entityManager->flush();
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function findAccess(string $id, UserDatabaseAccessRepository $accesses): ?UserDatabaseAccess
    {
        return Uuid::isValid($id) ? $accesses->find($id) : null;
    }
}
