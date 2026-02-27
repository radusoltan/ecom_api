<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderDelivered;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Order Aggregate Root.
 *
 * Business Rules:
 * - Order must have at least one order line
 * - Order must have shipping and billing addresses
 * - Order status transitions must be valid
 * - Cannot modify order after it's delivered or cancelled
 * - Total is sum of all order lines
 * - Promotions can be applied to reduce order total (max 3 stacked)
 * - Original total is preserved for reporting
 */
final class Order extends AggregateRoot
{
    private OrderId $id;
    private TenantId $tenantId;
    private string $customerEmail;
    private OrderStatus $status;
    /** @var OrderLine[] */
    private array $lines = [];
    private Address $shippingAddress;
    private Address $billingAddress;
    /** @var array<string, mixed> */
    private array $appliedPromotions = [];
    private ?Money $discountAmount = null;
    private ?string $couponCode = null;
    private ?Money $taxAmount = null;
    private ?string $taxJurisdiction = null;
    private ?string $taxRuleId = null;
    private float $taxRate = 0.0;
    private bool $isReverseCharge = false;
    private ?string $vatNumber = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function place(
        OrderId $id,
        TenantId $tenantId,
        string $customerEmail,
        array $lines,
        Address $shippingAddress,
        Address $billingAddress,
        array $appliedPromotions = [],
        ?Money $discountAmount = null,
        ?string $couponCode = null,
        ?Money $taxAmount = null,
        ?string $taxJurisdiction = null,
        ?string $taxRuleId = null,
        float $taxRate = 0.0,
        bool $isReverseCharge = false,
        ?string $vatNumber = null,
    ): self {
        if (empty($lines)) {
            throw new \InvalidArgumentException('Order must have at least one line item');
        }

        foreach ($lines as $line) {
            if (!$line instanceof OrderLine) {
                throw new \InvalidArgumentException('All order lines must be instances of OrderLine');
            }
        }

        if (false === filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Invalid customer email: "%s"', $customerEmail));
        }

        if (count($appliedPromotions) > 3) {
            throw new \InvalidArgumentException('Cannot apply more than 3 promotions to an order');
        }

        $order = new self();
        $order->id = $id;
        $order->tenantId = $tenantId;
        $order->customerEmail = $customerEmail;
        $order->status = OrderStatus::pending();
        $order->lines = $lines;
        $order->shippingAddress = $shippingAddress;
        $order->billingAddress = $billingAddress;
        $order->appliedPromotions = $appliedPromotions;
        $order->discountAmount = $discountAmount;
        $order->couponCode = $couponCode;
        $order->taxAmount = $taxAmount;
        $order->taxJurisdiction = $taxJurisdiction;
        $order->taxRuleId = $taxRuleId;
        $order->taxRate = $taxRate;
        $order->isReverseCharge = $isReverseCharge;
        $order->vatNumber = $vatNumber;
        $order->createdAt = new \DateTimeImmutable();
        $order->updatedAt = new \DateTimeImmutable();

        $order->recordEvent(new OrderPlaced(
            $order->id,
            $order->tenantId,
            $order->customerEmail,
            $order->total()
        ));

        return $order;
    }


    /**
     * @param OrderLine[] $lines
     */
    public static function createDraft(
        OrderId $id,
        TenantId $tenantId,
        string $customerEmail,
        array $lines,
        Address $shippingAddress,
        Address $billingAddress,
    ): self {
        if (empty($lines)) {
            throw new \InvalidArgumentException('Order must have at least one line item');
        }

        foreach ($lines as $line) {
            if (!$line instanceof OrderLine) {
                throw new \InvalidArgumentException('All order lines must be instances of OrderLine');
            }
        }

        if (false === filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Invalid customer email: "%s"', $customerEmail));
        }

        $order = new self();
        $order->id = $id;
        $order->tenantId = $tenantId;
        $order->customerEmail = $customerEmail;
        $order->status = OrderStatus::draft();
        $order->lines = $lines;
        $order->shippingAddress = $shippingAddress;
        $order->billingAddress = $billingAddress;
        $order->appliedPromotions = [];
        $order->createdAt = new \DateTimeImmutable();
        $order->updatedAt = new \DateTimeImmutable();

        return $order;
    }

    public static function reconstituteFromPersistence(
        OrderId $id,
        TenantId $tenantId,
        string $customerEmail,
        OrderStatus $status,
        array $lines,
        Address $shippingAddress,
        Address $billingAddress,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        array $appliedPromotions = [],
        ?Money $discountAmount = null,
        ?string $couponCode = null,
        ?Money $taxAmount = null,
        ?string $taxJurisdiction = null,
        ?string $taxRuleId = null,
        float $taxRate = 0.0,
        bool $isReverseCharge = false,
        ?string $vatNumber = null,
    ): self {
        $order = new self();
        $order->id = $id;
        $order->tenantId = $tenantId;
        $order->customerEmail = $customerEmail;
        $order->status = $status;
        $order->lines = $lines;
        $order->shippingAddress = $shippingAddress;
        $order->billingAddress = $billingAddress;
        $order->appliedPromotions = $appliedPromotions;
        $order->discountAmount = $discountAmount;
        $order->couponCode = $couponCode;
        $order->taxAmount = $taxAmount;
        $order->taxJurisdiction = $taxJurisdiction;
        $order->taxRuleId = $taxRuleId;
        $order->taxRate = $taxRate;
        $order->isReverseCharge = $isReverseCharge;
        $order->vatNumber = $vatNumber;
        $order->createdAt = $createdAt;
        $order->updatedAt = $updatedAt;

        return $order;
    }

    public function submitDraft(): void
    {
        $this->changeStatus(OrderStatus::pending());
    }

    public function markAsPaid(): void
    {
        $this->changeStatus(OrderStatus::paid());
    }

    public function startProcessing(): void
    {
        $this->changeStatus(OrderStatus::processing());
    }

    public function markAsShipped(): void
    {
        $this->changeStatus(OrderStatus::shipped());
    }

    public function markAsDelivered(string $deliveryMethod = 'standard'): void
    {
        $this->changeStatus(OrderStatus::delivered());

        // Record OrderDelivered event with additional delivery information
        $this->recordEvent(new OrderDelivered(
            $this->id,
            $this->tenantId,
            new \DateTimeImmutable(),
            $deliveryMethod,
            $this->customerEmail,
            new \DateTimeImmutable()
        ));
    }

    public function cancel(): void
    {
        if ($this->status->isFinal()) {
            throw new \InvalidArgumentException(sprintf('Cannot cancel order with status: %s', $this->status->value()));
        }

        if (!$this->status->canTransitionTo(OrderStatus::cancelled())) {
            throw new \InvalidArgumentException(sprintf('Cannot cancel order with status: %s. Can only cancel pending or processing orders', $this->status->value()));
        }

        $oldStatus = $this->status;
        $this->status = OrderStatus::cancelled();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new OrderCancelled($this->id, $this->tenantId, $oldStatus));
    }

    private function changeStatus(OrderStatus $newStatus): void
    {
        if ($this->status->isFinal()) {
            throw new \InvalidArgumentException(sprintf('Cannot change status of finalized order (current status: %s)', $this->status->value()));
        }

        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(sprintf('Invalid status transition from "%s" to "%s"', $this->status->value(), $newStatus->value()));
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new OrderStatusChanged($this->id, $this->tenantId, $oldStatus, $newStatus));
    }

    public function subtotal(): Money
    {
        $subtotal = Money::fromScalars(0, 'USD');

        foreach ($this->lines as $line) {
            $subtotal = $subtotal->add($line->total());
        }

        return $subtotal;
    }

    public function total(): Money
    {
        $subtotal = $this->subtotal();

        // Subtract discount from subtotal
        if (null !== $this->discountAmount) {
            $subtotal = $subtotal->subtract($this->discountAmount);
        }

        // Add tax to the discounted subtotal
        if (null !== $this->taxAmount) {
            return $subtotal->add($this->taxAmount);
        }

        return $subtotal;
    }

    public function discountAmount(): ?Money
    {
        return $this->discountAmount;
    }

    public function appliedPromotions(): array
    {
        return $this->appliedPromotions;
    }

    public function couponCode(): ?string
    {
        return $this->couponCode;
    }

    public function taxAmount(): ?Money
    {
        return $this->taxAmount;
    }

    public function taxJurisdiction(): ?string
    {
        return $this->taxJurisdiction;
    }

    public function taxRuleId(): ?string
    {
        return $this->taxRuleId;
    }

    public function taxRate(): float
    {
        return $this->taxRate;
    }

    public function isReverseCharge(): bool
    {
        return $this->isReverseCharge;
    }

    public function vatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customerEmail(): string
    {
        return $this->customerEmail;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function lines(): array
    {
        return $this->lines;
    }

    public function shippingAddress(): Address
    {
        return $this->shippingAddress;
    }

    public function billingAddress(): Address
    {
        return $this->billingAddress;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
