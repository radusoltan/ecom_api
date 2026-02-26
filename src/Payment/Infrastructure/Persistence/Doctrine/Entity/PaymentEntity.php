<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Presentation\Api\Processor\AuthorizePaymentProcessor;
use App\Payment\Presentation\Api\Processor\CancelPaymentProcessor;
use App\Payment\Presentation\Api\Processor\CapturePaymentProcessor;
use App\Payment\Presentation\Api\Processor\CreatePaymentProcessor;
use App\Payment\Presentation\Api\Processor\RefundPaymentProcessor;
use App\Payment\Presentation\Api\Provider\PaymentCollectionProvider;
use App\Payment\Presentation\Api\Provider\PaymentItemProvider;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_payments_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_payments_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_payments_status', columns: ['status'])]
#[ORM\Index(name: 'idx_payments_gateway', columns: ['gateway'])]
#[ORM\Index(name: 'idx_payments_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_payments_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_payments_retry', columns: ['status', 'next_retry_at', 'retry_count'])]
#[ApiResource(
    shortName: 'Payment',
    security: "is_granted('ROLE_USER')",
    operations: [
        new Get(
            provider: PaymentItemProvider::class
        ),
        new GetCollection(
            provider: PaymentCollectionProvider::class
        ),
        new Post(
            processor: CreatePaymentProcessor::class
        ),
        new Patch(
            uriTemplate: '/payments/{id}/authorize',
            processor: AuthorizePaymentProcessor::class
        ),
        new Patch(
            uriTemplate: '/payments/{id}/capture',
            processor: CapturePaymentProcessor::class
        ),
        new Patch(
            uriTemplate: '/payments/{id}/refund',
            processor: RefundPaymentProcessor::class
        ),
        new Patch(
            uriTemplate: '/payments/{id}/cancel',
            processor: CancelPaymentProcessor::class
        ),
    ]
)]
class PaymentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'order_id')]
    private string $orderId;

    #[ORM\Column(type: 'integer', nullable: false, name: 'amount_in_cents')]
    private int $amountInCents;

    #[ORM\Column(type: 'string', length: 3, nullable: false)]
    private string $currency;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $method;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $gateway;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $status;

    #[ORM\Column(type: 'encrypted_string', nullable: true, name: 'gateway_transaction_id')]
    private ?string $gatewayTransactionId = null;

    #[ORM\Column(type: 'encrypted_string', nullable: true, name: 'error_message')]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true, name: 'error_code')]
    private ?string $errorCode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, name: 'idempotency_key')]
    private ?string $idempotencyKey = null;

    #[ORM\Column(type: 'integer', nullable: false, options: ['default' => 0], name: 'refunded_amount_in_cents')]
    private int $refundedAmountInCents = 0;

    #[ORM\Column(type: 'integer', nullable: false, options: ['default' => 0], name: 'retry_count')]
    private int $retryCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'next_retry_at')]
    private ?\DateTimeImmutable $nextRetryAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: false, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false, name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(Payment $payment): self
    {
        $entity = new self();
        $entity->id = $payment->id()->toString();
        $entity->tenantId = $payment->tenantId()->toString();
        $entity->orderId = $payment->orderId();
        $entity->amountInCents = $payment->amountInCents();
        $entity->currency = $payment->currency();
        $entity->method = $payment->method()->value();
        $entity->gateway = $payment->gateway()->value();
        $entity->status = $payment->status()->value();
        $entity->gatewayTransactionId = $payment->gatewayTransactionId();
        $entity->errorMessage = $payment->errorMessage();
        $entity->errorCode = $payment->errorCode();
        $entity->idempotencyKey = $payment->idempotencyKey();
        $entity->refundedAmountInCents = $payment->refundedAmountInCents();
        $entity->retryCount = $payment->retryCount();
        $entity->nextRetryAt = $payment->nextRetryAt();
        $entity->createdAt = $payment->createdAt();
        $entity->updatedAt = $payment->updatedAt();

        return $entity;
    }

    public static function fromDTO(\App\Payment\Application\DTO\PaymentDTO $dto): self
    {
        $entity = new self();
        $entity->id = $dto->id;
        $entity->tenantId = $dto->tenantId;
        $entity->orderId = $dto->orderId;
        $entity->amountInCents = $dto->amountInCents;
        $entity->currency = $dto->currency;
        $entity->method = $dto->method;
        $entity->gateway = $dto->gateway;
        $entity->status = $dto->status;
        $entity->gatewayTransactionId = $dto->gatewayTransactionId;
        $entity->errorMessage = $dto->errorMessage;
        $entity->refundedAmountInCents = $dto->refundedAmountInCents;
        $entity->createdAt = $dto->createdAt;
        $entity->updatedAt = $dto->updatedAt;

        return $entity;
    }

    public function toDomainModel(): Payment
    {
        return Payment::reconstituteFromPersistence(
            id: PaymentId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            orderId: $this->orderId,
            amountInCents: $this->amountInCents,
            currency: $this->currency,
            method: PaymentMethod::fromString($this->method),
            gateway: PaymentGateway::fromString($this->gateway),
            status: PaymentStatus::fromString($this->status),
            gatewayTransactionId: $this->gatewayTransactionId,
            errorMessage: $this->errorMessage,
            refundedAmountInCents: $this->refundedAmountInCents,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            errorCode: $this->errorCode,
            retryCount: $this->retryCount,
            nextRetryAt: $this->nextRetryAt,
            idempotencyKey: $this->idempotencyKey
        );
    }

    public function updateFromDomainModel(Payment $payment): void
    {
        $this->orderId = $payment->orderId();
        $this->amountInCents = $payment->amountInCents();
        $this->currency = $payment->currency();
        $this->method = $payment->method()->value();
        $this->gateway = $payment->gateway()->value();
        $this->status = $payment->status()->value();
        $this->gatewayTransactionId = $payment->gatewayTransactionId();
        $this->errorMessage = $payment->errorMessage();
        $this->errorCode = $payment->errorCode();
        $this->idempotencyKey = $payment->idempotencyKey();
        $this->refundedAmountInCents = $payment->refundedAmountInCents();
        $this->retryCount = $payment->retryCount();
        $this->nextRetryAt = $payment->nextRetryAt();
        $this->updatedAt = $payment->updatedAt();
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getGatewayTransactionId(): ?string
    {
        return $this->gatewayTransactionId;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function getRefundedAmountInCents(): int
    {
        return $this->refundedAmountInCents;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getNextRetryAt(): ?\DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters (needed for API Platform deserialization)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function setAmountInCents(int $amountInCents): void
    {
        $this->amountInCents = $amountInCents;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function setGateway(string $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setGatewayTransactionId(?string $gatewayTransactionId): void
    {
        $this->gatewayTransactionId = $gatewayTransactionId;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function setErrorCode(?string $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function setIdempotencyKey(?string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function setRefundedAmountInCents(int $refundedAmountInCents): void
    {
        $this->refundedAmountInCents = $refundedAmountInCents;
    }

    public function setRetryCount(int $retryCount): void
    {
        $this->retryCount = $retryCount;
    }

    public function setNextRetryAt(?\DateTimeImmutable $nextRetryAt): void
    {
        $this->nextRetryAt = $nextRetryAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
