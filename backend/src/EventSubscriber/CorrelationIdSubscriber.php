<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Uid\Uuid;

final class CorrelationIdSubscriber
{
    private const ATTRIBUTE = '_correlation_id';
    private const HEADER = 'X-Correlation-ID';

    #[AsEventListener(priority: 100)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $provided = $event->getRequest()->headers->get(self::HEADER);
        $id = is_string($provided) && Uuid::isValid($provided) ? $provided : Uuid::v7()->toRfc4122();
        $event->getRequest()->attributes->set(self::ATTRIBUTE, $id);
    }

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $id = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (is_string($id)) {
            $event->getResponse()->headers->set(self::HEADER, $id);
        }
    }
}
