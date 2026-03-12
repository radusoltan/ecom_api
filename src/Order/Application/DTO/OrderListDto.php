<?php

declare(strict_types=1);

namespace App\Order\Application\DTO;

final readonly class OrderListDto
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $status,
        public string $customerEmail,
        public int $lineCount,
        public int $totalAmount,
        public string $totalCurrency,
        public ?int $discountAmount,
        public ?string $couponCode,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
