<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\Event\CustomerActivated;
use App\Customer\Domain\Event\CustomerAddressAdded;
use App\Customer\Domain\Event\CustomerAddressRemoved;
use App\Customer\Domain\Event\CustomerAddressUpdated;
use App\Customer\Domain\Event\CustomerConsentChanged;
use App\Customer\Domain\Event\CustomerCreated;
use App\Customer\Domain\Event\CustomerDeactivated;
use App\Customer\Domain\Event\CustomerPreferencesUpdated;
use App\Customer\Domain\Event\CustomerSegmentChanged;
use App\Customer\Domain\Event\CustomerUpdated;
use App\Customer\Domain\Event\DefaultAddressChanged;
use App\Customer\Domain\Event\LoyaltyPointsAwarded;
use App\Customer\Domain\Event\NotificationPreferencesUpdated;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerConsent;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerPreferences;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Domain\ValueObject\NotificationPreferences;
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
 * - Maximum 10 addresses per customer
 * - Only one default shipping address allowed
 * - Only one default billing address allowed
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
    private CustomerPreferences $preferences;
    private CustomerConsent $consents;
    private NotificationPreferences $notificationPreferences;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    /** @var array<string, CustomerAddress> */
    private array $addresses = [];

    private const MAX_ADDRESSES = 10;

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
        $customer->preferences = CustomerPreferences::create(); // Default preferences
        $customer->consents = CustomerConsent::default(); // Default GDPR consents (all false)
        $customer->notificationPreferences = NotificationPreferences::default(); // Default notification preferences
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

    /**
     * @param array<string, CustomerAddress> $addresses
     */
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
        CustomerPreferences $preferences,
        CustomerConsent $consents,
        NotificationPreferences $notificationPreferences,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        array $addresses = []
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
        $customer->preferences = $preferences;
        $customer->consents = $consents;
        $customer->notificationPreferences = $notificationPreferences;
        $customer->createdAt = $createdAt;
        $customer->updatedAt = $updatedAt;
        $customer->addresses = $addresses;

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

    public function updatePreferences(CustomerPreferences $preferences): void
    {
        $oldPreferences = $this->preferences->toArray();
        $this->preferences = $preferences;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerPreferencesUpdated(
            $this->id,
            $this->tenantId,
            $oldPreferences,
            $preferences->toArray(),
            new \DateTimeImmutable()
        ));
    }

    /**
     * Update a specific GDPR consent.
     *
     * Records the change as a domain event for audit trail and downstream processing.
     */
    public function updateConsent(ConsentType $type, bool $granted): void
    {
        // Update the appropriate consent based on type
        $this->consents = match ($type) {
            ConsentType::MARKETING_EMAIL => $this->consents->withMarketingEmail($granted),
            ConsentType::MARKETING_SMS => $this->consents->withMarketingSms($granted),
            ConsentType::THIRD_PARTY_SHARING => $this->consents->withThirdPartySharing($granted),
            ConsentType::ANALYTICS_TRACKING => $this->consents->withAnalyticsTracking($granted),
        };

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerConsentChanged(
            customerId: $this->id,
            tenantId: $this->tenantId,
            consentType: $type,
            granted: $granted,
            ipAddress: null, // Will be enriched by command handler
            occurredAt: new \DateTimeImmutable()
        ));
    }

    /**
     * Update notification preferences.
     *
     * Business Rules:
     * - Order updates and security alerts cannot be disabled
     * - Promotional offers require marketing email consent
     * - SMS preferences require phone number
     */
    public function updateNotificationPreferences(NotificationPreferences $newPreferences): void
    {
        // Validate promotional offers require marketing consent
        if ($newPreferences->promotionalOffers() && !$this->consents->marketingEmail()) {
            throw new \DomainException('Promotional offers notifications require marketing email consent');
        }

        // Validate SMS preferences require phone number
        if ($newPreferences->preferSms() && null === $this->phoneNumber) {
            throw new \DomainException('SMS notifications require a phone number to be set');
        }

        $oldPreferences = $this->notificationPreferences->toArray();
        $this->notificationPreferences = $newPreferences;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new NotificationPreferencesUpdated(
            customerId: $this->id,
            tenantId: $this->tenantId,
            oldPreferences: $oldPreferences,
            newPreferences: $newPreferences->toArray(),
            occurredAt: new \DateTimeImmutable()
        ));
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

    public function preferences(): CustomerPreferences
    {
        return $this->preferences;
    }

    public function getConsents(): CustomerConsent
    {
        return $this->consents;
    }

    public function getNotificationPreferences(): NotificationPreferences
    {
        return $this->notificationPreferences;
    }

    // Address Management Methods

    public function addAddress(CustomerAddress $address): void
    {
        if (\count($this->addresses) >= self::MAX_ADDRESSES) {
            throw new \DomainException(sprintf('Customer cannot have more than %d addresses', self::MAX_ADDRESSES));
        }

        $addressId = $address->id->toString();

        if (isset($this->addresses[$addressId])) {
            throw new \InvalidArgumentException(sprintf('Address with ID "%s" already exists', $addressId));
        }

        // If this address is marked as default shipping, unset any existing default shipping
        if ($address->isDefaultShipping) {
            $this->addresses = array_map(
                fn (CustomerAddress $addr) => $addr->withDefaultShipping(false),
                $this->addresses
            );
        }

        // If this address is marked as default billing, unset any existing default billing
        if ($address->isDefaultBilling) {
            $this->addresses = array_map(
                fn (CustomerAddress $addr) => $addr->withDefaultBilling(false),
                $this->addresses
            );
        }

        $this->addresses[$addressId] = $address;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerAddressAdded($this->id, $address));
    }

    public function updateAddress(CustomerAddressId $id, CustomerAddress $newAddress): void
    {
        $addressId = $id->toString();

        if (!isset($this->addresses[$addressId])) {
            throw new \InvalidArgumentException(sprintf('Address with ID "%s" not found', $addressId));
        }

        // Ensure the new address has the same ID
        if (!$newAddress->id->equals($id)) {
            throw new \InvalidArgumentException('Cannot change address ID during update');
        }

        // If this address is being set as default shipping, unset any existing default shipping
        if ($newAddress->isDefaultShipping) {
            $this->addresses = array_map(
                function (CustomerAddress $addr) use ($addressId) {
                    if ($addr->id->toString() === $addressId) {
                        return $addr; // Keep current address unchanged
                    }

                    return $addr->withDefaultShipping(false);
                },
                $this->addresses
            );
        }

        // If this address is being set as default billing, unset any existing default billing
        if ($newAddress->isDefaultBilling) {
            $this->addresses = array_map(
                function (CustomerAddress $addr) use ($addressId) {
                    if ($addr->id->toString() === $addressId) {
                        return $addr; // Keep current address unchanged
                    }

                    return $addr->withDefaultBilling(false);
                },
                $this->addresses
            );
        }

        $this->addresses[$addressId] = $newAddress;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerAddressUpdated($this->id, $id, $newAddress));
    }

    public function removeAddress(CustomerAddressId $id): void
    {
        $addressId = $id->toString();

        if (!isset($this->addresses[$addressId])) {
            throw new \InvalidArgumentException(sprintf('Address with ID "%s" not found', $addressId));
        }

        unset($this->addresses[$addressId]);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerAddressRemoved($this->id, $id));
    }

    public function setDefaultShippingAddress(CustomerAddressId $id): void
    {
        $addressId = $id->toString();

        if (!isset($this->addresses[$addressId])) {
            throw new \InvalidArgumentException(sprintf('Address with ID "%s" not found', $addressId));
        }

        // Unset all default shipping flags
        $this->addresses = array_map(
            fn (CustomerAddress $addr) => $addr->withDefaultShipping(false),
            $this->addresses
        );

        // Set the new default shipping
        $this->addresses[$addressId] = $this->addresses[$addressId]->withDefaultShipping(true);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DefaultAddressChanged($this->id, $id, 'shipping'));
    }

    public function setDefaultBillingAddress(CustomerAddressId $id): void
    {
        $addressId = $id->toString();

        if (!isset($this->addresses[$addressId])) {
            throw new \InvalidArgumentException(sprintf('Address with ID "%s" not found', $addressId));
        }

        // Unset all default billing flags
        $this->addresses = array_map(
            fn (CustomerAddress $addr) => $addr->withDefaultBilling(false),
            $this->addresses
        );

        // Set the new default billing
        $this->addresses[$addressId] = $this->addresses[$addressId]->withDefaultBilling(true);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new DefaultAddressChanged($this->id, $id, 'billing'));
    }

    /**
     * @return array<CustomerAddress>
     */
    public function getAddresses(): array
    {
        return array_values($this->addresses);
    }

    public function getDefaultShippingAddress(): ?CustomerAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->isDefaultShipping) {
                return $address;
            }
        }

        return null;
    }

    public function getDefaultBillingAddress(): ?CustomerAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->isDefaultBilling) {
                return $address;
            }
        }

        return null;
    }
}
