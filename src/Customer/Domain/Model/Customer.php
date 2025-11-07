<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\Event\CustomerActivated;
use App\Customer\Domain\Event\CustomerCreated;
use App\Customer\Domain\Event\CustomerDeactivated;
use App\Customer\Domain\Event\CustomerSegmentChanged;
use App\Customer\Domain\Event\CustomerUpdated;
use App\Customer\Domain\Event\LoyaltyPointsAwarded;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Customer Aggregate Root.
 *
 * Business Rules:
 * - Email must be unique per tenant
 * - First name and last name are required (2-50 chars each)
 * - Phone number is optional (E.164 format)
 * - Default segment is 'regular'
 * - Loyalty points start at 0 and cannot be negative
 * - Customer must be activated to place orders
 * - VIP segment requires manual upgrade
 */
final class Customer extends AggregateRoot
{
    private CustomerId $id;
    private TenantId $tenantId;
    private Email $email;
    private string $firstName;
    private string $lastName;
    private ?string $phoneNumber;
    private CustomerSegment $segment;
    private int $loyaltyPoints;
    private bool $isActive;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function register(
        CustomerId $id,
        TenantId $tenantId,
        Email $email,
        string $firstName,
        string $lastName,
        ?string $phoneNumber = null
    ): self {
        self::validateName($firstName, 'First name');
        self::validateName($lastName, 'Last name');

        if (null !== $phoneNumber) {
            self::validatePhoneNumber($phoneNumber);
        }

        $customer = new self();
        $customer->id = $id;
        $customer->tenantId = $tenantId;
        $customer->email = $email;
        $customer->firstName = trim($firstName);
        $customer->lastName = trim($lastName);
        $customer->phoneNumber = $phoneNumber;
        $customer->segment = CustomerSegment::regular();
        $customer->loyaltyPoints = 0;
        $customer->isActive = true; // Auto-activate on registration
        $customer->createdAt = new \DateTimeImmutable();
        $customer->updatedAt = new \DateTimeImmutable();

        $customer->recordEvent(new CustomerCreated(
            $customer->id,
            $customer->tenantId,
            $customer->email,
            $customer->firstName,
            $customer->lastName
        ));

        return $customer;
    }

    public static function reconstituteFromPersistence(
        CustomerId $id,
        TenantId $tenantId,
        Email $email,
        string $firstName,
        string $lastName,
        ?string $phoneNumber,
        CustomerSegment $segment,
        int $loyaltyPoints,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        $customer = new self();
        $customer->id = $id;
        $customer->tenantId = $tenantId;
        $customer->email = $email;
        $customer->firstName = $firstName;
        $customer->lastName = $lastName;
        $customer->phoneNumber = $phoneNumber;
        $customer->segment = $segment;
        $customer->loyaltyPoints = $loyaltyPoints;
        $customer->isActive = $isActive;
        $customer->createdAt = $createdAt;
        $customer->updatedAt = $updatedAt;

        return $customer;
    }

    public function updateProfile(
        string $firstName,
        string $lastName,
        ?string $phoneNumber = null
    ): void {
        self::validateName($firstName, 'First name');
        self::validateName($lastName, 'Last name');

        if (null !== $phoneNumber) {
            self::validatePhoneNumber($phoneNumber);
        }

        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->phoneNumber = $phoneNumber;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerUpdated(
            $this->id,
            $this->firstName,
            $this->lastName,
            $this->phoneNumber
        ));
    }

    public function changeSegment(CustomerSegment $newSegment): void
    {
        if ($this->segment->equals($newSegment)) {
            throw new \InvalidArgumentException(sprintf('Customer is already in segment: %s', $newSegment->value()));
        }

        $oldSegment = $this->segment;
        $this->segment = $newSegment;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerSegmentChanged(
            $this->id,
            $oldSegment,
            $newSegment
        ));
    }

    public function awardLoyaltyPoints(int $points, string $reason): void
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Loyalty points to award must be greater than 0');
        }

        $this->loyaltyPoints += $points;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new LoyaltyPointsAwarded(
            $this->id,
            $points,
            $this->loyaltyPoints,
            $reason
        ));
    }

    public function redeemLoyaltyPoints(int $points): void
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Loyalty points to redeem must be greater than 0');
        }

        if ($points > $this->loyaltyPoints) {
            throw new \InvalidArgumentException(sprintf('Insufficient loyalty points. Available: %d, Requested: %d', $this->loyaltyPoints, $points));
        }

        $this->loyaltyPoints -= $points;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        if ($this->isActive) {
            throw new \InvalidArgumentException(sprintf('Customer "%s %s" is already active', $this->firstName, $this->lastName));
        }

        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerActivated($this->id));
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            throw new \InvalidArgumentException(sprintf('Customer "%s %s" is already inactive', $this->firstName, $this->lastName));
        }

        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerDeactivated($this->id));
    }

    public function fullName(): string
    {
        return sprintf('%s %s', $this->firstName, $this->lastName);
    }

    private static function validateName(string $name, string $fieldName): void
    {
        $trimmed = trim($name);
        $length = mb_strlen($trimmed);

        if ($length < 2 || $length > 50) {
            throw new \InvalidArgumentException(sprintf('%s must be between 2 and 50 characters. Got: %d', $fieldName, $length));
        }
    }

    private static function validatePhoneNumber(string $phoneNumber): void
    {
        // E.164 format: +[country code][number] (max 15 digits)
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber)) {
            throw new \InvalidArgumentException(sprintf('Invalid phone number format. Must be E.164 format (e.g., +1234567890): "%s"', $phoneNumber));
        }
    }

    // Getters
    public function id(): CustomerId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function phoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function segment(): CustomerSegment
    {
        return $this->segment;
    }

    public function loyaltyPoints(): int
    {
        return $this->loyaltyPoints;
    }

    public function isActive(): bool
    {
        return $this->isActive;
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
