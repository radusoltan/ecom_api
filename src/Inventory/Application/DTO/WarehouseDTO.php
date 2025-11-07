<?php

declare(strict_types=1);

namespace App\Inventory\Application\DTO;

use App\Inventory\Domain\Model\Warehouse;

final readonly class WarehouseDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $code,
        public string $name,
        /** @var array<string, mixed> */
        public array $address,
        public int $priority,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromDomainModel(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id()->toString(),
            tenantId: $warehouse->tenantId()->toString(),
            code: $warehouse->code()->value(),
            name: $warehouse->name()->value(),
            address: [
                'street' => $warehouse->address()->street(),
                'city' => $warehouse->address()->city(),
                'state' => $warehouse->address()->state(),
                'postalCode' => $warehouse->address()->postalCode(),
                'country' => $warehouse->address()->country(),
            ],
            priority: $warehouse->priority(),
            isActive: $warehouse->isActive(),
            createdAt: $warehouse->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $warehouse->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
