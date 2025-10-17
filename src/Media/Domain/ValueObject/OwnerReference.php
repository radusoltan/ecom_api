<?php

declare(strict_types=1);

namespace App\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class OwnerReference
{
    private function __construct(
        private TenantId $tenantId,
        private OwnerType $type,
        private string $ownerId
    ) {}

    public static function from(TenantId $tenantId, OwnerType $type, string $ownerId): self
    {
        if ('' === trim($ownerId)) {
            throw new \InvalidArgumentException('Owner identifier cannot be empty.');
        }

        return new self($tenantId, $type, $ownerId);
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function type(): OwnerType
    {
        return $this->type;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }
}
