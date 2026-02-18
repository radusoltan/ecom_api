<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Blocks MFA-pending users from accessing any endpoint except /api/mfa/verify.
 *
 * This listener runs after the firewall resolves authentication (priority -10)
 * and checks the _mfa_pending request attribute set by MfaJwtEventListener.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)]
final class MfaAccessListener
{
    private const string MFA_VERIFY_PATH = '/api/mfa/verify';

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->attributes->get('_mfa_pending', false)) {
            return;
        }

        // Allow access to MFA verify endpoint only
        if (str_starts_with($request->getPathInfo(), self::MFA_VERIFY_PATH)) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => 'MFA verification required. Use POST /api/mfa/verify with your TOTP code.'],
            Response::HTTP_FORBIDDEN
        ));
    }
}
