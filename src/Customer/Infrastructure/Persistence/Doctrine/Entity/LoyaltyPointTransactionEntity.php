<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Loyalty Point Transaction Doctrine Entity.
 *
 * Infrastructure adapter for LoyaltyPointTransaction domain model.
 * This entity is immutable - no setters, only fromDomainModel().
 */
#[ORM\Entity]
#[ORM\Table(name: 'loyalty_point_transactions')]
#[ORM\Index(columns: ['customer_id'], name: 'idx_loyalty_transactions_customer')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_loyalty_transactions_tenant')]
#[ORM\Index(columns: ['customer_id', 'type'], name: 'idx_loyalty_transactions_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_loyalty_transactions_created')]
class LoyaltyPointTransactionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id = '';

    #[ORM\ManyToOne(targetEntity: CustomerEntity::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CustomerEntity $customer = null;

    #[ORM\Column(type: 'uuid', nullable: false)]
    private string $customerId = '';

    #[ORM\Column(type: 'uuid', nullable: false)]
    private string $tenantId = '';

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $type = '';

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $points = 0;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $balanceAfter = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $reason = '';

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $orderId = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    public static function fromDomainModel(LoyaltyPointTransaction $transaction): self
    {
        $entity = new self();
        $entity->id = $transaction->id()->toString();
        $entity->customerId = $transaction->customerId()->toString();
        $entity->tenantId = $transaction->tenantId()->toString();
        $entity->type = $transaction->type()->value;
        $entity->points = $transaction->points();
        $entity->balanceAfter = $transaction->balanceAfter();
        $entity->reason = $transaction->reason();
        $entity->orderId = $transaction->orderId();
        $entity->expiresAt = $transaction->expiresAt();
        $entity->createdAt = $transaction->createdAt();

        return $entity;
    }

    public function toDomainModel(): LoyaltyPointTransaction
    {
        return LoyaltyPointTransaction::reconstituteFromPersistence(
            LoyaltyPointTransactionId::fromString($this->id),
            CustomerId::fromString($this->customerId),
            TenantId::fromString($this->tenantId),
            TransactionType::fromString($this->type),
            $this->points,
            $this->balanceAfter,
            $this->reason,
            $this->orderId,
            $this->expiresAt,
            $this->createdAt
        );
    }

    // Getters only (immutable entity)
    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
