<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Event;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantName;

final readonly class TenantCreated
{
    public function __construct(
        public TenantId $tenantId,
        public TenantName $name,
        public Email $ownerEmail,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
