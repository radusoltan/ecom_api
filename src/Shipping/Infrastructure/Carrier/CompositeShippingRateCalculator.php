<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Carrier;

use App\Shipping\Domain\Service\CarrierAdapterInterface;
use App\Shipping\Domain\Service\ShippingRateCalculatorInterface;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Aggregates rates from all enabled carrier adapters.
 *
 * Collects rates from each registered carrier, returning all available
 * options sorted by price. Individual carrier failures are logged but
 * do not prevent other carriers from returning rates.
 */
final readonly class CompositeShippingRateCalculator implements ShippingRateCalculatorInterface
{
    /** @var array<string, CarrierAdapterInterface> */
    private array $adaptersByCode;

    /**
     * @param iterable<CarrierAdapterInterface> $adapters
     */
    public function __construct(
        #[AutowireIterator('app.carrier_adapter')]
        iterable $adapters,
        private LoggerInterface $logger,
    ) {
        $indexed = [];
        foreach ($adapters as $adapter) {
            $indexed[$adapter->carrierCode()->toString()] = $adapter;
        }
        $this->adaptersByCode = $indexed;
    }

    /** @return ShippingRate[] */
    public function calculateRates(string $originCountry, string $destinationCountry, float $weightKg): array
    {
        $allRates = [];

        foreach ($this->adaptersByCode as $code => $adapter) {
            if (!$adapter->isEnabled()) {
                continue;
            }

            try {
                $rates = $adapter->calculateRates($originCountry, $destinationCountry, $weightKg);
                $allRates = [...$allRates, ...$rates];
            } catch (\Throwable $e) {
                $this->logger->warning('Carrier rate calculation failed', [
                    'carrier' => $code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Sort by price ascending
        usort($allRates, static fn (ShippingRate $a, ShippingRate $b) => $a->amount()->compareTo($b->amount()));

        return $allRates;
    }

    public function generateTrackingNumber(CarrierCode $carrier): TrackingNumber
    {
        $code = $carrier->toString();

        if (isset($this->adaptersByCode[$code])) {
            return $this->adaptersByCode[$code]->generateTrackingNumber();
        }

        throw new \InvalidArgumentException(sprintf('No adapter registered for carrier "%s"', $code));
    }
}
