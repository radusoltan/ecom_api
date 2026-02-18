<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles MFA-related JWT events:
 * 1. JWTCreatedEvent: Downgrades roles to ROLE_MFA_PENDING for MFA-enabled users on login
 * 2. AuthenticationSuccessEvent: Adds mfa_required flag to login response
 * 3. JWTDecodedEvent: Stores mfa_pending flag on request attributes for access control
 */
final readonly class MfaJwtEventListener
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * On JWT creation during login, replace roles with ROLE_MFA_PENDING
     * if the user has MFA enabled (and this is not a post-MFA-verification token).
     */
    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', priority: -10)]
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Skip if this is a post-MFA verification token (set by MfaController)
        if ($request?->attributes->get('_mfa_verified', false)) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof UserEntity) {
            return;
        }

        $domainUser = $this->userRepository->findById(UserId::fromString($user->getId()));
        if (null === $domainUser || !$domainUser->mfaEnabled()) {
            return;
        }

        // Downgrade to MFA-pending partial token
        $payload = $event->getData();
        $payload['roles'] = ['ROLE_MFA_PENDING'];
        $payload['mfa_verified'] = false;
        // Short TTL: 5 minutes for MFA verification window
        $payload['exp'] = time() + 300;
        $event->setData($payload);
    }

    /**
     * On authentication success (login response), add mfa_required flag.
     */
    #[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success', priority: 10)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof UserEntity) {
            return;
        }

        $domainUser = $this->userRepository->findById(UserId::fromString($user->getId()));
        if (null === $domainUser || !$domainUser->mfaEnabled()) {
            return;
        }

        $data = $event->getData();
        $data['mfa_required'] = true;
        $event->setData($data);
    }

    /**
     * On JWT decoded (every authenticated request), check for mfa_verified=false
     * and store on request attributes for the MfaAccessListener.
     */
    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded')]
    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        if (isset($payload['mfa_verified']) && false === $payload['mfa_verified']) {
            $request = $this->requestStack->getCurrentRequest();
            $request?->attributes->set('_mfa_pending', true);
        }
    }
}
