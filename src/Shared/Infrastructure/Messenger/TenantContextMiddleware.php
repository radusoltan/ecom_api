<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Messenger middleware that automatically propagates tenant context across async boundaries.
 *
 * DISPATCH (producer):
 * - Reads current tenant from TenantContext
 * - Stamps message with TenantStamp
 *
 * HANDLE (consumer):
 * - Reads TenantStamp from envelope
 * - Restores tenant context before handler execution
 * - Clears context after handling
 */
final class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Check if this is a message being consumed (has ReceivedStamp)
        $isConsuming = null !== $envelope->last(ReceivedStamp::class);

        if ($isConsuming) {
            // CONSUMER: Restore tenant context from stamp
            $tenantStamp = $envelope->last(TenantStamp::class);

            if ($tenantStamp instanceof TenantStamp) {
                // Save previous tenant context to restore after handling.
                // This prevents nested message handling (e.g., sync transport
                // within a request) from destroying the request-level context.
                $previousTenantId = $this->tenantContext->getCurrentTenantId();

                $tenantId = TenantId::fromString($tenantStamp->getTenantId());
                $this->tenantContext->setCurrentTenant($tenantId);

                $this->logger->info('Tenant context restored for async message', [
                    'tenant_id' => $tenantStamp->getTenantId(),
                    'message_class' => get_class($envelope->getMessage()),
                ]);

                try {
                    // Handle message with tenant context
                    return $stack->next()->handle($envelope, $stack);
                } finally {
                    // Restore previous tenant context instead of clearing.
                    // This is critical for checkout flow safety: if event handlers
                    // dispatch messages via sync transport, clearing here would
                    // destroy the request-level tenant context, causing COALESCE
                    // RLS tables to return all tenants' data.
                    if (null !== $previousTenantId) {
                        $this->tenantContext->setCurrentTenant($previousTenantId);
                    } else {
                        $this->tenantContext->clearCurrentTenant();
                    }
                }
            } else {
                $this->logger->warning('Async message received without TenantStamp', [
                    'message_class' => get_class($envelope->getMessage()),
                ]);
            }
        } else {
            // PRODUCER: Add tenant stamp if not already present
            if (null === $envelope->last(TenantStamp::class)) {
                if ($this->tenantContext->hasCurrentTenant()) {
                    $tenantId = $this->tenantContext->getCurrentTenantId();
                    if (null !== $tenantId) {
                        $envelope = $envelope->with(new TenantStamp($tenantId->toString()));

                        $this->logger->debug('TenantStamp added to outgoing message', [
                            'tenant_id' => $tenantId->toString(),
                            'message_class' => get_class($envelope->getMessage()),
                        ]);
                    }
                }
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
