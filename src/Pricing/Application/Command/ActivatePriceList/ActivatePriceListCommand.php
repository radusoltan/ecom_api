<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ActivatePriceList;

use App\Pricing\Domain\Model\PriceListId;

final readonly class ActivatePriceListCommand
{
    public function __construct(
        public PriceListId $priceListId,
    ) {
    }
}
