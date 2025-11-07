<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Rate Limiter for Stock Operations.
 *
 * Applies rate limiting to stock operation endpoints to prevent abuse:
 * - Reserve/Allocate/Release operations: 100 requests per minute per user
 * - Per-tenant limit: 1000 requests per minute
 * - Reservations: Token bucket with 50 tokens, refills at 50/minute
 *
 * Returns HTTP 429 (Too Many Requests) when limits are exceeded.
 */
final readonly class StockOperationRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $stockOperationsLimiter,
        private RateLimiterFactory $stockOperationsPerTenantLimiter,
        private RateLimiterFactory $stockReservationsLimiter,
        private RateLimiterFactory $bulkOperationsLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 10],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply to stock operation endpoints
        if (!$this->isStockOperationRoute($request->getPathInfo())) {
            return;
        }

        // Get user identifier (IP + User ID if authenticated)
        $userIdentifier = $this->getUserIdentifier($request);

        // Get tenant identifier from header
        $tenantId = $request->headers->get('X-Tenant-ID');

        // Apply per-user rate limit
        $limiter = $this->stockOperationsLimiter->create($userIdentifier);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), sprintf('Too many stock operations. Please retry after %d seconds.', $limit->getRetryAfter()->getTimestamp() - time()));
        }

        // Apply per-tenant rate limit
        if (null !== $tenantId) {
            $tenantLimiter = $this->stockOperationsPerTenantLimiter->create($tenantId);
            $tenantLimit = $tenantLimiter->consume(1);

            if (!$tenantLimit->isAccepted()) {
                throw new TooManyRequestsHttpException($tenantLimit->getRetryAfter()->getTimestamp() - time(), sprintf('Tenant rate limit exceeded. Please retry after %d seconds.', $tenantLimit->getRetryAfter()->getTimestamp() - time()));
            }
        }

        // Apply stricter limits for reservation endpoints
        if ($this->isReservationRoute($request->getPathInfo())) {
            $reservationLimiter = $this->stockReservationsLimiter->create($userIdentifier.'_reservation');
            $reservationLimit = $reservationLimiter->consume(1);

            if (!$reservationLimit->isAccepted()) {
                throw new TooManyRequestsHttpException($reservationLimit->getRetryAfter()->getTimestamp() - time(), sprintf('Too many reservation requests. Please retry after %d seconds.', $reservationLimit->getRetryAfter()->getTimestamp() - time()));
            }
        }

        // Apply very strict limits for bulk operations
        if ($this->isBulkOperationRoute($request->getPathInfo())) {
            $bulkLimiter = $this->bulkOperationsLimiter->create($userIdentifier.'_bulk');
            $bulkLimit = $bulkLimiter->consume(1);

            if (!$bulkLimit->isAccepted()) {
                throw new TooManyRequestsHttpException($bulkLimit->getRetryAfter()->getTimestamp() - time(), sprintf('Too many bulk operation requests. Please retry after %d seconds.', $bulkLimit->getRetryAfter()->getTimestamp() - time()));
            }
        }

        // Add rate limit headers to response
        $event->getRequest()->attributes->set('rate_limit_remaining', $limit->getRemainingTokens());
        $event->getRequest()->attributes->set('rate_limit_limit', $limit->getLimit());
        $event->getRequest()->attributes->set('rate_limit_reset', $limit->getRetryAfter()->getTimestamp());
    }

    private function isStockOperationRoute(string $path): bool
    {
        return str_starts_with($path, '/api/stock/') || str_starts_with($path, '/api/stock-items');
    }

    private function isReservationRoute(string $path): bool
    {
        return str_starts_with($path, '/api/stock/reserve');
    }

    private function isBulkOperationRoute(string $path): bool
    {
        return str_contains($path, '/bulk-');
    }

    private function getUserIdentifier(\Symfony\Component\HttpFoundation\Request $request): string
    {
        // Try to get authenticated user
        $user = $request->attributes->get('_user');
        if (null !== $user) {
            return 'user_'.$user;
        }

        // Fallback to IP address
        return 'ip_'.$request->getClientIp();
    }
}
