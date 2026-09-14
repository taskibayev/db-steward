<?php

namespace App\Controller;

use App\Connection\ConnectionView;
use App\Entity\User;
use App\Repository\ClientConnectionRepository;
use App\Repository\UserDatabaseAccessRepository;
use App\User\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ConnectionController extends AbstractController
{
    #[Route('/api/connections', name: 'api_connections_list', methods: ['GET'])]
    public function list(ClientConnectionRepository $connections, UserDatabaseAccessRepository $accesses): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (UserRole::Administrator === $user->getRole()) {
            $available = array_filter($connections->findAllOrdered(), static fn ($connection): bool => $connection->isActive());
        } else {
            $available = array_map(
                static fn ($access) => $access->getConnection(),
                array_filter($accesses->findForUser($user), static fn ($access): bool => $access->getConnection()->isActive()),
            );
        }

        return $this->json(['items' => array_map(ConnectionView::fromEntity(...), $available)]);
    }
}
