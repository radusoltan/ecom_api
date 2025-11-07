<?php

declare(strict_types=1);

namespace App\Tenant\Application\DTO;

use App\Tenant\Domain\Model\Tenant;

final readonly class TenantDTO
{
    /**
     * @param array<string> $enabledLocales
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $ownerEmail,
        public string $status,
        public string $createdAt,
        public string $defaultLocale,
        public array $enabledLocales
    ) {
    }

    public static function fromAggregate(Tenant $tenant): self
    {
        $enabledLocales = array_map(
            fn ($locale) => $locale->value(),
            $tenant->enabledLocales()
        );

        return new self(
            $tenant->id()->toString(),
            $tenant->name()->value(),
            $tenant->ownerEmail()->value(),
            $tenant->status()->value(),
            $tenant->createdAt()->format('Y-m-d H:i:s'),
            $tenant->defaultLocale()->value(),
            $enabledLocales
        );
    }
}
