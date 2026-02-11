<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Global API rate limiter that applies different limits based on route patterns and authentication status.
 *
 * Rate Limits:
 * - Public API (unauthenticated): 100 req/min per IP
 * - Authenticated API: 1000 req/min per tenant
 * - Admin API: 500 req/min per user
 * - Search API: 200 req/min per tenant
 * - Checkout API: 50 req/min per session
 */
final readonly class GlobalApiRateLimitListener implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $apiPublicLimiter,
        private RateLimiterFactory $apiAuthenticatedLimiter,
        private RateLimiterFactory $apiAdminLimiter,
        private RateLimiterFactory $apiSearchLimiter,
        private RateLimiterFactory $apiCheckoutLimiter,
        private ?TokenStorageInterface $tokenStorage = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10], // Before security
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only apply to API routes
        if (!str_starts_with($path, '/api')) {
            return;
        }

        // Determine which rate limiter to use based on route
        $limiterConfig = $this->determineRateLimiter($path);
        if (null === $limiterConfig) {
            return; // No rate limiting for this route
        }

        [$limiter, $limiterKey] = $limiterConfig;

        // Consume 1 token
        $limit = $limiter->create($limiterKey)->consume(1);

        // Set rate limit info in request attributes for header subscriber
        $request->attributes->set('rate_limit_limit', $limit->getLimit());
        $request->attributes->set('rate_limit_remaining', $limit->getRemainingTokens());
        $request->attributes->set('rate_limit_reset', $limit->getRetryAfter()->getTimestamp());

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            $this->logger?->warning('Rate limit exceeded', [
                'path' => $path,
                'limiter_key' => $limiterKey,
                'retry_after' => $retryAfter,
                'ip' => $request->getClientIp(),
            ]);

            $response = new JsonResponse(
                [
                    'type' => 'https://tools.ietf.org/html/rfc6585#section-4',
                    'title' => 'Too Many Requests',
                    'status' => 429,
                    'detail' => sprintf(
                        'Rate limit exceeded. Please retry in %d seconds.',
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
            );

            $event->setResponse($response);
        }
    }

    /**
     * Determine which rate limiter to use and the limiter key based on route and authentication.
     *
     * @return array{RateLimiterFactory, string}|null
     */
    private function determineRateLimiter(string $path): ?array
    {
        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

        // Checkout routes - most restrictive (per session)
        if (preg_match('#^/api/(orders|checkout|cart)#', $path)) {
            // Use session ID if available, otherwise fallback to IP
            $sessionId = $request->hasSession() && $request->getSession()->isStarted()
                ? $request->getSession()->getId()
                : ($request->getClientIp() ?? 'no-session');
            $tenantId = $request->headers->get('X-Tenant-ID', 'default');

            return [$this->apiCheckoutLimiter, sprintf('%s:%s', $sessionId, $tenantId)];
        }

        // Search routes - per tenant
        if (preg_match('#^/api/(search|products/search)#', $path)) {
            $tenantId = $request->headers->get('X-Tenant-ID', 'default');

            return [$this->apiSearchLimiter, $tenantId];
        }

        // Admin routes - per user (if authenticated)
        if (preg_match('#^/api/admin#', $path)) {
            $user = $this->tokenStorage?->getToken()?->getUser();
            $userId = is_object($user) && method_exists($user, 'getUserIdentifier')
                ? $user->getUserIdentifier()
                : 'anonymous';

            return [$this->apiAdminLimiter, $userId];
        }

        // Authenticated API routes - per tenant
        if ($request->headers->has('X-Tenant-ID')) {
            $tenantId = $request->headers->get('X-Tenant-ID');

            return [$this->apiAuthenticatedLimiter, $tenantId];
        }

        // Public API routes - per IP
        $clientIp = $request->getClientIp() ?? 'unknown';

        return [$this->apiPublicLimiter, $clientIp];
    }
}
