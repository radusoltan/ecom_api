<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\Model\LoyaltyPointTransaction;

/**
 * Loyalty Point Transaction Data Transfer Object.
 *
 * Serializable representation of a loyalty point transaction.
 */
final readonly class LoyaltyPointTransactionDTO
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $tenantId,
        public string $type,
        public string $typeLabel,
        public int $points,
        public int $balanceAfter,
        public string $reason,
        public ?string $orderId,
        public ?string $expiresAt,
        public string $createdAt,
        public bool $isDebit,
        public bool $isCredit
    ) {
    }

    public static function fromDomainModel(LoyaltyPointTransaction $transaction): self
    {
        return new self(
            id: $transaction->id()->toString(),
            customerId: $transaction->customerId()->toString(),
            tenantId: $transaction->tenantId()->toString(),
            type: $transaction->type()->value,
            typeLabel: $transaction->type()->label(),
            points: $transaction->points(),
            balanceAfter: $transaction->balanceAfter(),
            reason: $transaction->reason(),
            orderId: $transaction->orderId(),
            expiresAt: $transaction->expiresAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $transaction->createdAt()->format(\DateTimeInterface::ATOM),
            isDebit: $transaction->isDebit(),
            isCredit: $transaction->isCredit()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customerId,
            'tenantId' => $this->tenantId,
            'type' => $this->type,
            'typeLabel' => $this->typeLabel,
            'points' => $this->points,
            'balanceAfter' => $this->balanceAfter,
            'reason' => $this->reason,
            'orderId' => $this->orderId,
            'expiresAt' => $this->expiresAt,
            'createdAt' => $this->createdAt,
            'isDebit' => $this->isDebit,
            'isCredit' => $this->isCredit,
        ];
    }
}
