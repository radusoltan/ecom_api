<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Dedicated rate limiter for authentication endpoints (login, register, password reset).
 *
 * Applies a strict limit of 10 requests/minute per client IP to prevent:
 * - Brute-force login attacks
 * - Registration spam / account enumeration
 * - Password reset email bombardment
 *
 * Runs at priority 20 (before GlobalApiRateLimitListener at 10) so auth
 * endpoints get their own stricter limit instead of the general API limit.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class AuthRateLimitListener
{
    /** @var list<string> */
    private const array AUTH_PATH_PREFIXES = [
        '/api/login',
        '/api/v1/auth/register',
        '/api/v1/auth/password',
    ];

    public function __construct(
        private RateLimiterFactoryInterface $apiAuthLimiter,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!$this->isAuthEndpoint($path)) {
            return;
        }

        // Rate limit by client IP (auth endpoints are unauthenticated)
        $clientIp = $request->getClientIp() ?? 'unknown';
        $limiterKey = sprintf('auth:%s', $clientIp);

        $limiter = $this->apiAuthLimiter->create($limiterKey);
        $limit = $limiter->consume(1);

        // Store rate limit info for header subscriber
        $request->attributes->set('rate_limit_limit', $limit->getLimit());
        $request->attributes->set('rate_limit_remaining', $limit->getRemainingTokens());
        $request->attributes->set('rate_limit_reset', $limit->getRetryAfter()->getTimestamp());

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            $this->logger?->warning('Auth rate limit exceeded', [
                'path' => $path,
                'ip' => $clientIp,
                'retry_after' => $retryAfter,
            ]);

            $event->setResponse(new JsonResponse(
                [
                    'type' => 'https://tools.ietf.org/html/rfc6585#section-4',
                    'title' => 'Too Many Requests',
                    'status' => 429,
                    'detail' => sprintf(
                        'Too many authentication attempts. Please retry in %d seconds.',
                        $retryAfter
                    ),
                    'retry_after' => $retryAfter,
                ],
                429,
                [
                    'Retry-After' => (string) $retryAfter,
                    'X-RateLimit-Limit' => (string) $limit->getLimit(),
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset' => (string) $limit->getRetryAfter()->getTimestamp(),
                ]
            ));
        }
    }

    private function isAuthEndpoint(string $path): bool
    {
        foreach (self::AUTH_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
