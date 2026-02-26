<?php

declare(strict_types=1);

namespace App\Shipping\Application\Query\GetShippingRates;

use App\Shipping\Domain\Service\ShippingRateCalculatorInterface;
use App\Shipping\Domain\ValueObject\ShippingRate;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetShippingRatesHandler
{
    public function __construct(
        private ShippingRateCalculatorInterface $rateCalculator,
    ) {
    }

    /** @return ShippingRate[] */
    public function __invoke(GetShippingRatesQuery $query): array
    {
        return $this->rateCalculator->calculateRates(
            $query->originCountry,
            $query->destinationCountry,
            $query->weightKg,
        );
    }
}
