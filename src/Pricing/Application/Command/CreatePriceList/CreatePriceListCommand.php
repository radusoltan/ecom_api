<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\CreatePriceList;

use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;

/**
 * Command: Create a new PriceList
 */
final readonly class CreatePriceListCommand
{
    public function __construct(
        public PriceListId $priceListId,
        public TenantId $tenantId,
        public PriceListName $name,
        public int $priority = 100,
        public ?DateTimeImmutable $validFrom = null,
        public ?DateTimeImmutable $validTo = null
    ) {
    }
}
