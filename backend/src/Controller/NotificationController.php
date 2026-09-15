<?php

namespace App\Controller;

use App\Dto\AuditHistoryQuery;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\NotificationView;
use App\Notification\RealtimeTokenFactory;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class NotificationController extends AbstractController
{
    #[Route('/api/notifications', methods: ['GET'])]
    public function list(NotificationRepository $repository, #[MapQueryString] AuditHistoryQuery $query = new AuditHistoryQuery()): JsonResponse
    {
        $user = $this->user();
        $total = $repository->countFor($user);

        return $this->json([
            'items' => array_map(NotificationView::fromEntity(...), $repository->findPage($user, $query->page, $query->pageSize)),
            'unreadCount' => $repository->countUnread($user),
            'pagination' => ['page' => $query->page, 'pageSize' => $query->pageSize, 'total' => $total, 'pages' => max(1, (int) ceil($total / $query->pageSize))],
        ]);
    }

    #[Route('/api/notifications/{id}/read', methods: ['POST'])]
    public function markRead(string $id, NotificationRepository $repository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->user();
        $notification = Uuid::isValid($id) ? $repository->find($id) : null;
        if (!$notification instanceof Notification || !$notification->getRecipient()->getId()->equals($user->getId())) {
            return $this->json(['error' => 'notification_not_found'], Response::HTTP_NOT_FOUND);
        }
        $notification->markRead();
        $entityManager->flush();

        return $this->json(['notification' => NotificationView::fromEntity($notification)]);
    }

    #[Route('/api/notifications/read-all', methods: ['POST'])]
    public function markAllRead(NotificationRepository $repository, EntityManagerInterface $entityManager): JsonResponse
    {
        $updated = $repository->markAllRead($this->user());
        $entityManager->flush();

        return $this->json(['updated' => $updated]);
    }

    #[Route('/api/realtime/token', methods: ['GET'])]
    public function token(RealtimeTokenFactory $tokens): JsonResponse
    {
        return $this->json($tokens->create($this->user()));
    }

    private function user(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
