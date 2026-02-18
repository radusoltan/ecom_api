<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class TenantDeactivated
{
    public function __construct(
        public TenantId $tenantId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
