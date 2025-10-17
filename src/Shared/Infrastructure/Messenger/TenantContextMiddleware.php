<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Tenant\TenantContext;
use App\Tenant\Domain\ValueObject\TenantId;
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
        private readonly LoggerInterface $logger
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Check if this is a message being consumed (has ReceivedStamp)
        $isConsuming = null !== $envelope->last(ReceivedStamp::class);

        if ($isConsuming) {
            // CONSUMER: Restore tenant context from stamp
            $tenantStamp = $envelope->last(TenantStamp::class);

            if ($tenantStamp instanceof TenantStamp) {
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
                    // Clear tenant context after handling
                    $this->tenantContext->clearCurrentTenant();
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
                    $envelope = $envelope->with(new TenantStamp($tenantId->toString()));

                    $this->logger->debug('TenantStamp added to outgoing message', [
                        'tenant_id' => $tenantId->toString(),
                        'message_class' => get_class($envelope->getMessage()),
                    ]);
                }
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
