<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Exception;

final class InvalidShipmentTransitionException extends \DomainException
{
    public static function fromTransition(string $from, string $to): self
    {
        return new self(sprintf('Invalid shipment status transition from "%s" to "%s"', $from, $to));
    }
}
