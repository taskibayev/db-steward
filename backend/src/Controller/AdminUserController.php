<?php

namespace App\Controller;

use App\Dto\CreateManagerRequest;
use App\Dto\UpdateUserStatusRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\User\UserRole;
use App\User\UserView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin/users')]
final class AdminUserController extends AbstractController
{
    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(UserRepository $users): JsonResponse
    {
        return $this->json(['items' => array_map(UserView::fromEntity(...), $users->findAllOrdered())]);
    }

    #[Route('', name: 'api_admin_users_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreateManagerRequest $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (null !== $users->findByEmail($request->email)) {
            return $this->json(['error' => 'email_already_exists'], Response::HTTP_CONFLICT);
        }

        $user = new User($request->email, UserRole::Manager);
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['user' => UserView::fromEntity($user)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/status', name: 'api_admin_users_status', methods: ['PATCH'])]
    public function status(
        string $id,
        #[MapRequestPayload]
        UpdateUserStatusRequest $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!Uuid::isValid($id) || !$user = $users->find($id)) {
            return $this->json(['error' => 'user_not_found'], Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        if ($currentUser === $user && !$request->active) {
            return $this->json(['error' => 'cannot_disable_self'], Response::HTTP_CONFLICT);
        }

        $user->setActive($request->active);
        $entityManager->flush();

        return $this->json(['user' => UserView::fromEntity($user)]);
    }
}
