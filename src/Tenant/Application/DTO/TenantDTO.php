<?php

declare(strict_types=1);

namespace App\Tenant\Application\DTO;

use App\Tenant\Domain\Model\Tenant;

final readonly class TenantDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $ownerEmail,
        public string $status,
        public string $createdAt
    ) {
    }

    public static function fromAggregate(Tenant $tenant): self
    {
        return new self(
            $tenant->id()->toString(),
            $tenant->name()->value(),
            $tenant->ownerEmail()->value(),
            $tenant->status()->value(),
            $tenant->createdAt()->format('Y-m-d H:i:s')
        );
    }
}
