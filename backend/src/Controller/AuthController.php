<?php

namespace App\Controller;

use App\Auth\OAuthProviderRegistry;
use App\Auth\OAuthSessionAuthenticator;
use App\Entity\OAuthIdentity;
use App\Entity\User;
use App\Repository\UserRepository;
use App\User\UserView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AuthController extends AbstractController
{
    #[Route('/api/auth/config', name: 'api_auth_config', methods: ['GET'])]
    public function config(OAuthProviderRegistry $providers): JsonResponse
    {
        return $this->json(['providers' => $providers->names()]);
    }

    #[Route('/api/auth/csrf', name: 'api_auth_csrf', methods: ['GET'])]
    public function csrf(CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        return $this->json(['token' => $csrfTokenManager->getToken('api')->getValue()]);
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'authenticated' => $user instanceof User,
            'user' => $user instanceof User ? UserView::fromEntity($user) : null,
        ]);
    }

    #[Route('/api/auth/oauth/{provider}/start', name: 'api_auth_oauth_start', methods: ['GET'])]
    public function start(string $provider, Request $request, OAuthProviderRegistry $providers): Response
    {
        $adapter = $providers->get($provider);
        if (null === $adapter) {
            return $this->json(['error' => 'oauth_provider_not_found'], Response::HTTP_NOT_FOUND);
        }

        $state = bin2hex(random_bytes(32));
        $request->getSession()->set('oauth_state_'.$provider, $state);

        return new RedirectResponse($adapter->authorizationUrl($state, $this->callbackUri($request, $provider), $request));
    }

    #[Route('/api/auth/oauth/{provider}/callback', name: 'api_auth_oauth_callback', methods: ['GET'])]
    public function callback(
        string $provider,
        Request $request,
        OAuthProviderRegistry $providers,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $adapter = $providers->get($provider);
        if (null === $adapter) {
            return $this->oauthFailure('provider_not_found');
        }

        $expectedState = $request->getSession()->remove('oauth_state_'.$provider);
        $state = $request->query->getString('state');
        if (!is_string($expectedState) || '' === $state || !hash_equals($expectedState, $state)) {
            return $this->oauthFailure('invalid_state');
        }

        $code = $request->query->getString('code');
        if ('' === $code) {
            return $this->oauthFailure('provider_rejected');
        }

        try {
            $profile = $adapter->fetchProfile($code, $this->callbackUri($request, $provider), $request);
        } catch (\Throwable) {
            return $this->oauthFailure('provider_error');
        }

        if (!$profile->emailVerified) {
            return $this->oauthFailure('email_not_verified');
        }

        $user = $users->findByEmail($profile->email);
        if (null === $user || !$user->isActive()) {
            return $this->oauthFailure('access_denied');
        }

        $identityRepository = $entityManager->getRepository(OAuthIdentity::class);
        $identity = $identityRepository->findOneBy(['provider' => $provider, 'subject' => $profile->subject]);
        if ($identity instanceof OAuthIdentity && $identity->getUser() !== $user) {
            return $this->oauthFailure('identity_conflict');
        }

        if (!$identity instanceof OAuthIdentity) {
            $identity = new OAuthIdentity($user, $provider, $profile->subject);
            $entityManager->persist($identity);
        } else {
            $identity->touch();
        }

        $user->recordLogin();
        $entityManager->flush();
        $security->login($user, OAuthSessionAuthenticator::class);

        return new RedirectResponse('/');
    }

    private function callbackUri(Request $request, string $provider): string
    {
        return $request->getSchemeAndHttpHost().'/api/auth/oauth/'.rawurlencode($provider).'/callback';
    }

    private function oauthFailure(string $reason): RedirectResponse
    {
        return new RedirectResponse('/?auth_error='.rawurlencode($reason));
    }
}
