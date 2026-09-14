<?php

namespace App\Controller;

use App\Connection\ConnectionManager;
use App\Connection\ConnectionView;
use App\Connection\DuplicateConnectionName;
use App\Dto\CreateConnectionRequest;
use App\Dto\UpdateConnectionRequest;
use App\Dto\UpdateConnectionStatusRequest;
use App\Entity\ClientConnection;
use App\Repository\ClientConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin/connections')]
final class AdminConnectionController extends AbstractController
{
    #[Route('', name: 'api_admin_connections_list', methods: ['GET'])]
    public function list(ClientConnectionRepository $connections): JsonResponse
    {
        return $this->json(['items' => array_map(ConnectionView::fromEntity(...), $connections->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_connections_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateConnectionRequest $request, ConnectionManager $manager): JsonResponse
    {
        try {
            $connection = $manager->create($request);
        } catch (DuplicateConnectionName) {
            return $this->json(['error' => 'connection_name_exists'], Response::HTTP_CONFLICT);
        }

        return $this->json(['connection' => ConnectionView::fromEntity($connection)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_connections_update', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload]
        UpdateConnectionRequest $request,
        ClientConnectionRepository $connections,
        ConnectionManager $manager,
    ): JsonResponse {
        $connection = $this->findConnection($id, $connections);
        if (!$connection instanceof ClientConnection) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $manager->update($connection, $request);
        } catch (DuplicateConnectionName) {
            return $this->json(['error' => 'connection_name_exists'], Response::HTTP_CONFLICT);
        }

        return $this->json(['connection' => ConnectionView::fromEntity($connection)]);
    }

    #[Route('/{id}/status', name: 'api_admin_connections_status', methods: ['PATCH'])]
    public function status(
        string $id,
        #[MapRequestPayload]
        UpdateConnectionStatusRequest $request,
        ClientConnectionRepository $connections,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $connection = $this->findConnection($id, $connections);
        if (!$connection instanceof ClientConnection) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }
        $connection->setActive($request->active);
        $entityManager->flush();

        return $this->json(['connection' => ConnectionView::fromEntity($connection)]);
    }

    #[Route('/{id}/test', name: 'api_admin_connections_test', methods: ['POST'])]
    public function test(string $id, ClientConnectionRepository $connections, ConnectionManager $manager): JsonResponse
    {
        $connection = $this->findConnection($id, $connections);
        if (!$connection instanceof ClientConnection) {
            return $this->json(['error' => 'connection_not_found'], Response::HTTP_NOT_FOUND);
        }
        $result = $manager->test($connection);

        return $this->json(
            [
                'connection' => ConnectionView::fromEntity($connection),
                'error' => $result->errorCode,
            ],
            $result->successful ? Response::HTTP_OK : Response::HTTP_BAD_GATEWAY,
        );
    }

    private function findConnection(string $id, ClientConnectionRepository $connections): ?ClientConnection
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $connections->find($id);
    }
}
