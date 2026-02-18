<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\Model\Customer;
use App\Shared\Domain\ValueObject\Money;

/**
 * Customer Summary DTO.
 *
 * Lightweight DTO for customer list views.
 */
final readonly class CustomerSummaryDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $phoneNumber,
        public string $segment,
        public int $loyaltyPoints,
        public bool $isActive,
        public string $status,
        public int $orderCount,
        public ?Money $totalSpent,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromDomainModel(
        Customer $customer,
        int $orderCount = 0,
        ?Money $totalSpent = null,
    ): self {
        return new self(
            id: $customer->id()->toString(),
            tenantId: $customer->tenantId()->toString(),
            email: $customer->email()->toString(),
            firstName: $customer->firstName(),
            lastName: $customer->lastName(),
            fullName: $customer->fullName(),
            phoneNumber: $customer->phoneNumber(),
            segment: $customer->segment()->value(),
            loyaltyPoints: $customer->loyaltyPoints(),
            isActive: $customer->isActive(),
            status: $customer->isActive() ? 'active' : 'inactive',
            orderCount: $orderCount,
            totalSpent: $totalSpent,
            createdAt: $customer->createdAt(),
            updatedAt: $customer->updatedAt()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->fullName,
            'phoneNumber' => $this->phoneNumber,
            'segment' => $this->segment,
            'loyaltyPoints' => $this->loyaltyPoints,
            'isActive' => $this->isActive,
            'status' => $this->status,
            'orderCount' => $this->orderCount,
            'totalSpent' => $this->totalSpent?->getAmount(),
            'totalSpentCurrency' => $this->totalSpent?->getCurrency()->getCurrencyCode(),
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
