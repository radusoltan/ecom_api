<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Messenger stamp that carries tenant context across async message boundaries.
 *
 * When a message is dispatched to a queue, the current tenant ID is stamped onto it.
 * The consumer then restores this tenant context before processing the message.
 *
 * This ensures tenant isolation is maintained even in async processing scenarios.
 */
final readonly class TenantStamp implements StampInterface
{
    public function __construct(
        private string $tenantId
    ) {}

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
