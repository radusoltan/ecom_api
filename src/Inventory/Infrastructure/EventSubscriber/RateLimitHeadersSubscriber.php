<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds Rate Limit Headers to API Responses.
 *
 * Adds standard rate limit headers:
 * - X-RateLimit-Limit: Maximum requests allowed
 * - X-RateLimit-Remaining: Requests remaining in current window
 * - X-RateLimit-Reset: Unix timestamp when limit resets
 */
final class RateLimitHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only add headers if rate limiting was applied
        if (!$request->attributes->has('rate_limit_remaining')) {
            return;
        }

        $response->headers->set('X-RateLimit-Limit', (string) $request->attributes->get('rate_limit_limit'));
        $response->headers->set('X-RateLimit-Remaining', (string) $request->attributes->get('rate_limit_remaining'));
        $response->headers->set('X-RateLimit-Reset', (string) $request->attributes->get('rate_limit_reset'));
    }
}
