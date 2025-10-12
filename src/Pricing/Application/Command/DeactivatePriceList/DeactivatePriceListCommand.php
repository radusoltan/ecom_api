<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\DeactivatePriceList;

use App\Pricing\Domain\Model\PriceListId;

final readonly class DeactivatePriceListCommand
{
    public function __construct(
        public PriceListId $priceListId
    ) {
    }
}
