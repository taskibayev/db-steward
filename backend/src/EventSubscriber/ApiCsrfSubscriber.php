<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ApiCsrfSubscriber
{
    public function __construct(private readonly CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 12)]
    public function validate(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if (Request::METHOD_GET === $request->getMethod() || Request::METHOD_HEAD === $request->getMethod()) {
            return;
        }

        $token = new CsrfToken('api', $request->headers->get('X-CSRF-Token', ''));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $event->setResponse(new JsonResponse(['error' => 'invalid_csrf_token'], Response::HTTP_FORBIDDEN));
        }
    }
}
