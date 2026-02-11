<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Payment\Domain\Model\PaymentId;
use App\Payment\Domain\Model\Transaction;
use App\Payment\Domain\Model\TransactionId;
use App\Payment\Domain\Model\TransactionStatus;
use App\Payment\Domain\Model\TransactionType;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'transactions')]
// Performance indexes for common queries
#[ORM\Index(name: 'idx_transactions_payment_id', columns: ['payment_id'])]
#[ORM\Index(name: 'idx_transactions_type', columns: ['type'])]
#[ORM\Index(name: 'idx_transactions_status', columns: ['status'])]
#[ORM\Index(name: 'idx_transactions_gateway', columns: ['gateway_transaction_id'])]
#[ORM\Index(name: 'idx_transactions_created_at', columns: ['created_at'])]
#[ApiResource(
    shortName: 'Transaction',
    operations: [
        new Get(),
        new GetCollection(),
    ]
)]
class TransactionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'payment_id')]
    private string $paymentId;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $type;

    #[ORM\Column(type: 'integer', nullable: false, name: 'amount_in_cents')]
    private int $amountInCents;

    #[ORM\Column(type: 'string', length: 3, nullable: false)]
    private string $currency;

    #[ORM\Column(type: 'string', length: 255, nullable: false, name: 'gateway_transaction_id')]
    private string $gatewayTransactionId;

    #[ORM\Column(type: 'text', nullable: true, name: 'gateway_response')]
    private ?string $gatewayResponse = null;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $status;

    #[ORM\Column(type: 'string', length: 100, nullable: true, name: 'error_code')]
    private ?string $errorCode = null;

    #[ORM\Column(type: 'text', nullable: true, name: 'error_message')]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: false, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public static function fromDomainModel(Transaction $transaction): self
    {
        $entity = new self();
        $entity->id = $transaction->id()->toString();
        $entity->paymentId = $transaction->paymentId()->toString();
        $entity->type = $transaction->type()->value;
        $entity->amountInCents = $transaction->amount()->getAmount();
        $entity->currency = $transaction->amount()->getCurrency()->getCurrencyCode();
        $entity->gatewayTransactionId = $transaction->gatewayTransactionId();
        $entity->gatewayResponse = $transaction->gatewayResponse();
        $entity->status = $transaction->status()->value;
        $entity->errorCode = $transaction->errorCode();
        $entity->errorMessage = $transaction->errorMessage();
        $entity->createdAt = $transaction->createdAt();

        return $entity;
    }

    public function toDomainModel(): Transaction
    {
        return Transaction::reconstituteFromPersistence(
            id: TransactionId::fromString($this->id),
            paymentId: PaymentId::fromString($this->paymentId),
            type: TransactionType::from($this->type),
            amount: Money::fromScalars($this->amountInCents, $this->currency),
            gatewayTransactionId: $this->gatewayTransactionId,
            gatewayResponse: $this->gatewayResponse,
            status: TransactionStatus::from($this->status),
            errorCode: $this->errorCode,
            errorMessage: $this->errorMessage,
            createdAt: $this->createdAt
        );
    }

    // Getters for API Platform serialization
    public function getId(): string
    {
        return $this->id;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getGatewayTransactionId(): string
    {
        return $this->gatewayTransactionId;
    }

    public function getGatewayResponse(): ?string
    {
        return $this->gatewayResponse;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Setters (needed for API Platform deserialization)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setPaymentId(string $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setAmountInCents(int $amountInCents): void
    {
        $this->amountInCents = $amountInCents;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function setGatewayTransactionId(string $gatewayTransactionId): void
    {
        $this->gatewayTransactionId = $gatewayTransactionId;
    }

    public function setGatewayResponse(?string $gatewayResponse): void
    {
        $this->gatewayResponse = $gatewayResponse;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setErrorCode(?string $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
