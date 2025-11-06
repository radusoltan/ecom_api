<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Payment Aggregate Root
 *
 * Manages payment lifecycle:
 * - Create: Initialize payment for an order
 * - Authorize: Reserve funds (pre-authorization)
 * - Capture: Complete payment and transfer funds
 * - Refund: Return funds to customer
 * - Cancel: Cancel before capture
 * - Fail: Handle payment failures
 *
 * Business Rules:
 * - Payment amount must match order total
 * - Status transitions must follow valid state machine
 * - Gateway transaction ID required after authorization
 * - Refunds only allowed for captured payments
 * - Partial refunds supported (up to captured amount)
 * - Multi-currency support (amount stored in cents)
 */
final class Payment extends AggregateRoot
{
    private PaymentId $id;
    private TenantId $tenantId;
    private string $orderId; // Reference to Order aggregate
    private int $amountInCents;
    private string $currency;
    private PaymentMethod $method;
    private PaymentGateway $gateway;
    private PaymentStatus $status;
    private ?string $gatewayTransactionId;
    private ?string $errorMessage;
    private int $refundedAmountInCents;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        PaymentId $id,
        TenantId $tenantId,
        string $orderId,
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        PaymentGateway $gateway
    ): self {
        self::validateAmount($amountInCents);
        self::validateCurrency($currency);

        $payment = new self();
        $payment->id = $id;
        $payment->tenantId = $tenantId;
        $payment->orderId = $orderId;
        $payment->amountInCents = $amountInCents;
        $payment->currency = strtoupper($currency);
        $payment->method = $method;
        $payment->gateway = $gateway;
        $payment->status = PaymentStatus::pending();
        $payment->gatewayTransactionId = null;
        $payment->errorMessage = null;
        $payment->refundedAmountInCents = 0;
        $payment->createdAt = new DateTimeImmutable();
        $payment->updatedAt = new DateTimeImmutable();

        $payment->recordEvent(new PaymentCreated(
            $payment->id,
            $payment->tenantId,
            $payment->orderId,
            $payment->amountInCents,
            $payment->currency,
            $payment->gateway->value()
        ));

        return $payment;
    }

    public static function reconstituteFromPersistence(
        PaymentId $id,
        TenantId $tenantId,
        string $orderId,
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        PaymentGateway $gateway,
        PaymentStatus $status,
        ?string $gatewayTransactionId,
        ?string $errorMessage,
        int $refundedAmountInCents,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        $payment = new self();
        $payment->id = $id;
        $payment->tenantId = $tenantId;
        $payment->orderId = $orderId;
        $payment->amountInCents = $amountInCents;
        $payment->currency = $currency;
        $payment->method = $method;
        $payment->gateway = $gateway;
        $payment->status = $status;
        $payment->gatewayTransactionId = $gatewayTransactionId;
        $payment->errorMessage = $errorMessage;
        $payment->refundedAmountInCents = $refundedAmountInCents;
        $payment->createdAt = $createdAt;
        $payment->updatedAt = $updatedAt;

        return $payment;
    }

    public function authorize(string $gatewayTransactionId): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::authorized())) {
            throw new InvalidArgumentException(
                sprintf('Cannot authorize payment in status: %s', $this->status->value())
            );
        }

        if (empty($gatewayTransactionId)) {
            throw new InvalidArgumentException('Gateway transaction ID is required for authorization');
        }

        $this->status = PaymentStatus::authorized();
        $this->gatewayTransactionId = $gatewayTransactionId;
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new PaymentAuthorized(
            $this->id,
            $this->tenantId,
            $gatewayTransactionId
        ));
    }

    public function capture(): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::captured())) {
            throw new InvalidArgumentException(
                sprintf('Cannot capture payment in status: %s', $this->status->value())
            );
        }

        $this->status = PaymentStatus::captured();
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new PaymentCaptured(
            $this->id,
            $this->tenantId,
            $this->amountInCents,
            $this->orderId
        ));
    }

    public function refund(int $refundAmountInCents, string $reason): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::refunded())) {
            throw new InvalidArgumentException(
                sprintf('Cannot refund payment in status: %s', $this->status->value())
            );
        }

        if ($refundAmountInCents <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than 0');
        }

        $maxRefundable = $this->amountInCents - $this->refundedAmountInCents;
        if ($refundAmountInCents > $maxRefundable) {
            throw new InvalidArgumentException(
                sprintf(
                    'Refund amount (%d) exceeds available amount (%d)',
                    $refundAmountInCents,
                    $maxRefundable
                )
            );
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Refund reason is required');
        }

        $this->refundedAmountInCents += $refundAmountInCents;
        $this->status = PaymentStatus::refunded();
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new PaymentRefunded(
            $this->id,
            $refundAmountInCents,
            $reason
        ));
    }

    public function cancel(string $reason): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::cancelled())) {
            throw new InvalidArgumentException(
                sprintf('Cannot cancel payment in status: %s', $this->status->value())
            );
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Cancellation reason is required');
        }

        $this->status = PaymentStatus::cancelled();
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new PaymentCancelled(
            $this->id,
            $reason
        ));
    }

    public function markAsFailed(string $errorMessage): void
    {
        if (!$this->status->canTransitionTo(PaymentStatus::failed())) {
            throw new InvalidArgumentException(
                sprintf('Cannot mark payment as failed in status: %s', $this->status->value())
            );
        }

        if (empty($errorMessage)) {
            throw new InvalidArgumentException('Error message is required');
        }

        $this->status = PaymentStatus::failed();
        $this->errorMessage = $errorMessage;
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new PaymentFailed(
            $this->id,
            $errorMessage
        ));
    }

    private static function validateAmount(int $amountInCents): void
    {
        if ($amountInCents <= 0) {
            throw new InvalidArgumentException(
                sprintf('Payment amount must be greater than 0. Got: %d cents', $amountInCents)
            );
        }

        // Maximum amount: $1,000,000.00 (100,000,000 cents)
        if ($amountInCents > 100_000_000) {
            throw new InvalidArgumentException(
                sprintf('Payment amount exceeds maximum allowed ($1,000,000.00). Got: %d cents', $amountInCents)
            );
        }
    }

    private static function validateCurrency(string $currency): void
    {
        // ISO 4217 currency codes (3 uppercase letters)
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(
                sprintf('Invalid currency code: "%s". Must be 3 uppercase letters (ISO 4217)', $currency)
            );
        }
    }

    // Getters
    public function id(): PaymentId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function amountInCents(): int
    {
        return $this->amountInCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function gatewayTransactionId(): ?string
    {
        return $this->gatewayTransactionId;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function refundedAmountInCents(): int
    {
        return $this->refundedAmountInCents;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
