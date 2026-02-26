<?php

declare(strict_types=1);

namespace App\Shipping\Application\Command\CreateShipment;

final readonly class CreateShipmentCommand
{
    /** @param array<string, mixed> $recipientAddress */
    public function __construct(
        public string $shipmentId,
        public string $tenantId,
        public string $orderId,
        public string $recipientName,
        public array $recipientAddress,
        public ?int $rateAmount = null,
        public ?string $rateCurrency = null,
        public ?string $rateCarrier = null,
        public ?int $rateEstimatedDays = null,
        public ?string $rateServiceName = null,
    ) {
    }
}
