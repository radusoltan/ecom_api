<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Tenant\TenantContext;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Monolog processor that adds tenant context to all log entries.
 *
 * Enriches log records with:
 * - tenant_id: Current tenant identifier
 * - request_id: Unique request identifier
 * - user_id: Current user identifier (when authentication implemented)
 * - route: Current route name
 * - method: HTTP method
 * - uri: Request URI
 *
 * This enables:
 * - Per-tenant log filtering
 * - Request tracing across services
 * - Compliance and audit trails
 */
final readonly class TenantContextProcessor
{
    public function __construct(
        private TenantContext $tenantContext,
        private RequestStack $requestStack
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        // Add tenant_id if available
        if ($this->tenantContext->hasCurrentTenant()) {
            $extra['tenant_id'] = $this->tenantContext->getCurrentTenantId()->toString();
        } else {
            $extra['tenant_id'] = null;
        }

        // Add request context if available
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            // Generate or retrieve request ID
            $requestId = $request->headers->get('X-Request-ID');
            if (!$requestId) {
                $requestId = $this->generateRequestId();
                $request->headers->set('X-Request-ID', $requestId);
            }

            $extra['request_id'] = $requestId;
            $extra['method'] = $request->getMethod();
            $extra['uri'] = $request->getRequestUri();
            $extra['route'] = $request->attributes->get('_route');
            $extra['status_code'] = $request->attributes->get('_response_status_code');

            // Add client IP (for security logs)
            $extra['client_ip'] = $request->getClientIp();
        } else {
            $extra['request_id'] = null;
            $extra['method'] = 'CLI';
            $extra['uri'] = null;
            $extra['route'] = null;
        }

        // User ID will be added here when authentication is implemented
        $extra['user_id'] = null;

        // Add timestamp in ISO8601 format
        $extra['timestamp'] = $record->datetime->format(\DateTimeInterface::ATOM);

        return $record->with(extra: $extra);
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16)); // 32 character hex string
    }
}
