<?php

declare(strict_types=1);

namespace App\Shipping\Domain\ValueObject;

use Brick\Money\Money;

final readonly class ShippingRate
{
    private function __construct(
        private Money $amount,
        private CarrierCode $carrier,
        private int $estimatedDays,
        private string $serviceName,
    ) {
        if ($estimatedDays < 0) {
            throw new \InvalidArgumentException('Estimated days cannot be negative');
        }
        if ('' === trim($serviceName)) {
            throw new \InvalidArgumentException('Service name cannot be empty');
        }
    }

    public static function create(Money $amount, CarrierCode $carrier, int $estimatedDays, string $serviceName): self
    {
        return new self($amount, $carrier, $estimatedDays, $serviceName);
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function carrier(): CarrierCode
    {
        return $this->carrier;
    }

    public function estimatedDays(): int
    {
        return $this->estimatedDays;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function equals(self $other): bool
    {
        return $this->amount->isEqualTo($other->amount)
            && $this->carrier->equals($other->carrier)
            && $this->estimatedDays === $other->estimatedDays
            && $this->serviceName === $other->serviceName;
    }
}
