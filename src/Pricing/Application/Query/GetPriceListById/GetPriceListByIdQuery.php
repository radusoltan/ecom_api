<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetPriceListById;

use App\Pricing\Domain\Model\PriceListId;

final readonly class GetPriceListByIdQuery
{
    public function __construct(
        public PriceListId $priceListId
    ) {
    }
}
