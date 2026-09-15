<?php

namespace App\MessageHandler;

use App\Entity\TemporaryQueryResult;
use App\Message\ExpireQueryResult;
use App\Repository\TemporaryQueryResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class ExpireQueryResultHandler
{
    public function __construct(
        private readonly TemporaryQueryResultRepository $results,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ExpireQueryResult $message): void
    {
        $result = $this->results->find($message->resultId);
        if (!$result instanceof TemporaryQueryResult) {
            return;
        }
        $remaining = $result->getExpiresAt()->getTimestamp() - time();
        if (0 < $remaining) {
            $this->bus->dispatch($message, [new DelayStamp($remaining * 1000)]);

            return;
        }
        $this->entityManager->remove($result);
        $this->entityManager->flush();
    }
}
