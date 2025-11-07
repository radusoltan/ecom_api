<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Event;

use DateTimeImmutable;
use App\Tenant\Domain\ValueObject\TenantId;

final readonly class TenantReactivated
{
    public function __construct(
        public TenantId $tenantId,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
