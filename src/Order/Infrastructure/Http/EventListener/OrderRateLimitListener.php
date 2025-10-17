<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

/**
 * OrderRateLimitListener
 *
 * Applies rate limiting to order placement endpoints to prevent abuse.
 *
 * Business Rules:
 * - Limit: 10 requests per minute per IP
 * - Applies to: POST /api/orders
 * - Returns: 429 Too Many Requests with Retry-After header
 * - Metrics: Exposed as order_rate_limit_hits_total
 *
 * @see PRD Section 5.3: Rate Limiting Requirements
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 10)]
final readonly class OrderRateLimitListener
{
    public function __construct(
        private RateLimiterFactory $ordersPlaceLimiter,
        private LoggerInterface $logger
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only apply to POST /api/orders
        if ($request->getMethod() !== 'POST' || !str_starts_with($request->getPathInfo(), '/api/orders')) {
            return;
        }

        // Skip rate limiting for /api/orders/{id}/... operations (status, cancel)
        if (preg_match('#^/api/orders/[^/]+/#', $request->getPathInfo())) {
            return;
        }

        // Create limiter key based on IP + Tenant
        $clientIp = $request->getClientIp() ?? 'unknown';
        $tenantId = $request->headers->get('X-Tenant-ID', 'default');
        $limiterKey = sprintf('%s:%s', $clientIp, $tenantId);

        $limiter = $this->ordersPlaceLimiter->create($limiterKey);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->warning('Order placement rate limit exceeded', [
                'ip' => $clientIp,
                'tenant_id' => $tenantId,
                'path' => $request->getPathInfo(),
                'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
            ]);

            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            $response = new JsonResponse(
                [
                    'type' => 'https://tools.ietf.org/html/rfc6585#section-4',
                    'title' => 'Too Many Requests',
                    'status' => Response::HTTP_TOO_MANY_REQUESTS,
                    'detail' => sprintf(
                        'Rate limit exceeded. Please retry in %d seconds.',
                        max(1, $retryAfter)
                    ),
                    'retry_after' => max(1, $retryAfter),
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => (string) max(1, $retryAfter),
                    'X-RateLimit-Limit' => (string) $limit->getLimit(),
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset' => (string) $limit->getRetryAfter()->getTimestamp(),
                ]
            );

            $event->setResponse($response);
            return;
        }

        // Add rate limit headers to successful requests
        $request->attributes->set('_rate_limit_remaining', $limit->getRemainingTokens());
        $request->attributes->set('_rate_limit_limit', $limit->getLimit());
    }
}
