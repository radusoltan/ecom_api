<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerConsent;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerPreferences;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Domain\ValueObject\NotificationPreferences;
use App\Customer\Presentation\Api\Processor\ActivateCustomerProcessor;
use App\Customer\Presentation\Api\Processor\AwardLoyaltyPointsProcessor;
use App\Customer\Presentation\Api\Processor\ChangeSegmentProcessor;
use App\Customer\Presentation\Api\Processor\DeactivateCustomerProcessor;
use App\Customer\Presentation\Api\Processor\RegisterCustomerProcessor;
use App\Customer\Presentation\Api\Processor\UpdateCustomerProcessor;
use App\Customer\Presentation\Api\Provider\CustomerCollectionProvider;
use App\Customer\Presentation\Api\Provider\CustomerItemProvider;
use App\Customer\Presentation\Api\Provider\CustomerOrdersProvider;
use App\Customer\Presentation\Api\Provider\ProfileProvider;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\Index(columns: ['tenant_id'])]
#[ORM\Index(columns: ['email_blind_index'])]
#[ORM\Index(columns: ['segment'])]
#[ORM\Index(columns: ['is_active'])]
#[ORM\UniqueConstraint(name: 'unique_email_per_tenant_bi', columns: ['tenant_id', 'email_blind_index'])]
#[ApiResource(
    shortName: 'Customer',
    security: "is_granted('ROLE_USER')",
    operations: [
        new Get(provider: CustomerItemProvider::class),
        new GetCollection(provider: CustomerCollectionProvider::class),
        new Post(processor: RegisterCustomerProcessor::class),
        new Put(processor: UpdateCustomerProcessor::class),
        new Patch(
            uriTemplate: '/customers/{id}/activate',
            processor: ActivateCustomerProcessor::class
        ),
        new Patch(
            uriTemplate: '/customers/{id}/deactivate',
            processor: DeactivateCustomerProcessor::class
        ),
        new Patch(
            uriTemplate: '/customers/{id}/segment',
            processor: ChangeSegmentProcessor::class
        ),
        new Post(
            uriTemplate: '/customers/{id}/award-points',
            processor: AwardLoyaltyPointsProcessor::class
        ),
        new Get(
            uriTemplate: '/customers/{id}/orders',
            provider: CustomerOrdersProvider::class
        ),
        new Delete(),
        // Profile endpoint (authenticated user's own data)
        new Get(
            uriTemplate: '/profile',
            provider: ProfileProvider::class
        ),
    ]
)]
class CustomerEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId = '';

    #[ORM\Column(type: 'encrypted_string')]
    private string $email = '';

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $emailBlindIndex = null;

    #[ORM\Column(type: 'encrypted_string')]
    private string $firstName = '';

    #[ORM\Column(type: 'encrypted_string')]
    private string $lastName = '';

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $segment = '';

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $loyaltyPoints = 0;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $isActive = true;

    #[ORM\Column(type: 'json', nullable: false)]
    private string $preferences = '{}';

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $consentMarketingEmail = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $consentMarketingSms = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $consentThirdParty = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $consentAnalytics = false;

    // Notification Preferences
    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $notifOrderUpdates = true;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $notifShippingUpdates = true;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $notifPromotionalOffers = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $notifPriceDropAlerts = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $notifBackInStockAlerts = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $notifSecurityAlerts = true;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $notifNewsletterWeekly = false;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $notifPreferSms = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, CustomerAddressEntity>
     */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: CustomerAddressEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $addressEntities;

    /**
     * Temporary field to accept password during registration (not persisted in DB)
     * Used only for User creation.
     */
    private ?string $plainPassword = null;

    public function __construct()
    {
        $this->addressEntities = new ArrayCollection();
    }

    public static function fromDomainModel(Customer $customer): self
    {
        $entity = new self();
        $entity->id = $customer->id()->toString();
        $entity->tenantId = $customer->tenantId()->toString();
        $entity->email = $customer->email()->toString();
        $entity->firstName = $customer->firstName();
        $entity->lastName = $customer->lastName();
        $entity->phoneNumber = $customer->phoneNumber();
        $entity->segment = $customer->segment()->toString();
        $entity->loyaltyPoints = $customer->loyaltyPoints();
        $entity->isActive = $customer->isActive();
        $encoded = json_encode($customer->preferences()->toArray());
        if (false === $encoded) {
            throw new \RuntimeException('Failed to encode customer preferences to JSON');
        }
        $entity->preferences = $encoded;

        // Map consents
        $consents = $customer->getConsents();
        $entity->consentMarketingEmail = $consents->marketingEmail();
        $entity->consentMarketingSms = $consents->marketingSms();
        $entity->consentThirdParty = $consents->thirdPartySharing();
        $entity->consentAnalytics = $consents->analyticsTracking();

        // Map notification preferences
        $notifPrefs = $customer->getNotificationPreferences();
        $entity->notifOrderUpdates = $notifPrefs->orderUpdates();
        $entity->notifShippingUpdates = $notifPrefs->shippingUpdates();
        $entity->notifPromotionalOffers = $notifPrefs->promotionalOffers();
        $entity->notifPriceDropAlerts = $notifPrefs->priceDropAlerts();
        $entity->notifBackInStockAlerts = $notifPrefs->backInStockAlerts();
        $entity->notifSecurityAlerts = $notifPrefs->securityAlerts();
        $entity->notifNewsletterWeekly = $notifPrefs->newsletterWeekly();
        $entity->notifPreferSms = $notifPrefs->preferSms();

        $entity->createdAt = $customer->createdAt();
        $entity->updatedAt = $customer->updatedAt();

        return $entity;
    }

    public function updateFromDomainModel(Customer $customer): void
    {
        // Note: id, tenantId, email should never change
        $this->firstName = $customer->firstName();
        $this->lastName = $customer->lastName();
        $this->phoneNumber = $customer->phoneNumber();
        $this->segment = $customer->segment()->toString();
        $this->loyaltyPoints = $customer->loyaltyPoints();
        $this->isActive = $customer->isActive();
        $encoded = json_encode($customer->preferences()->toArray());
        if (false === $encoded) {
            throw new \RuntimeException('Failed to encode customer preferences to JSON');
        }
        $this->preferences = $encoded;

        // Update consents
        $consents = $customer->getConsents();
        $this->consentMarketingEmail = $consents->marketingEmail();
        $this->consentMarketingSms = $consents->marketingSms();
        $this->consentThirdParty = $consents->thirdPartySharing();
        $this->consentAnalytics = $consents->analyticsTracking();

        // Update notification preferences
        $notifPrefs = $customer->getNotificationPreferences();
        $this->notifOrderUpdates = $notifPrefs->orderUpdates();
        $this->notifShippingUpdates = $notifPrefs->shippingUpdates();
        $this->notifPromotionalOffers = $notifPrefs->promotionalOffers();
        $this->notifPriceDropAlerts = $notifPrefs->priceDropAlerts();
        $this->notifBackInStockAlerts = $notifPrefs->backInStockAlerts();
        $this->notifSecurityAlerts = $notifPrefs->securityAlerts();
        $this->notifNewsletterWeekly = $notifPrefs->newsletterWeekly();
        $this->notifPreferSms = $notifPrefs->preferSms();

        $this->updatedAt = $customer->updatedAt();
    }

    public function toDomainModel(): Customer
    {
        $preferencesData = json_decode($this->preferences, true);
        $preferences = is_array($preferencesData)
            ? CustomerPreferences::fromArray($preferencesData)
            : CustomerPreferences::create();

        // Reconstitute consents
        $consents = CustomerConsent::create(
            marketingEmail: $this->consentMarketingEmail,
            marketingSms: $this->consentMarketingSms,
            thirdPartySharing: $this->consentThirdParty,
            analyticsTracking: $this->consentAnalytics
        );

        // Reconstitute notification preferences
        $notificationPreferences = NotificationPreferences::create(
            orderUpdates: $this->notifOrderUpdates,
            shippingUpdates: $this->notifShippingUpdates,
            promotionalOffers: $this->notifPromotionalOffers,
            priceDropAlerts: $this->notifPriceDropAlerts,
            backInStockAlerts: $this->notifBackInStockAlerts,
            securityAlerts: $this->notifSecurityAlerts,
            newsletterWeekly: $this->notifNewsletterWeekly,
            preferSms: $this->notifPreferSms
        );

        // Reconstitute addresses from entity collection
        $addresses = [];
        foreach ($this->addressEntities as $addressEntity) {
            if ($addressEntity->isDeleted()) {
                continue;
            }
            $addr = CustomerAddress::create(
                id: CustomerAddressId::fromString($addressEntity->getId()),
                street: $addressEntity->getStreet(),
                street2: $addressEntity->getStreet2(),
                city: $addressEntity->getCity(),
                state: $addressEntity->getState(),
                postalCode: $addressEntity->getPostalCode(),
                country: $addressEntity->getCountry(),
                type: AddressType::fromString($addressEntity->getType()),
                isDefaultShipping: $addressEntity->isDefaultShipping(),
                isDefaultBilling: $addressEntity->isDefaultBilling(),
            );
            $addresses[$addressEntity->getId()] = $addr;
        }

        return Customer::reconstituteFromPersistence(
            CustomerId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            Email::fromString($this->email),
            $this->firstName,
            $this->lastName,
            $this->phoneNumber,
            CustomerSegment::fromString($this->segment),
            $this->loyaltyPoints,
            $this->isActive,
            $preferences,
            $consents,
            $notificationPreferences,
            $this->createdAt,
            $this->updatedAt,
            $addresses
        );
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function getLoyaltyPoints(): int
    {
        return $this->loyaltyPoints;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function getPreferences(): string
    {
        return $this->preferences;
    }

    /**
     * @return Collection<int, CustomerAddressEntity>
     */
    public function getAddressEntities(): Collection
    {
        return $this->addressEntities;
    }

    public function addAddressEntity(CustomerAddressEntity $address): void
    {
        if (!$this->addressEntities->contains($address)) {
            $this->addressEntities->add($address);
            $address->setCustomer($this);
        }
    }

    public function removeAddressEntity(CustomerAddressEntity $address): void
    {
        if ($this->addressEntities->contains($address)) {
            $this->addressEntities->removeElement($address);
        }
    }

    // Setters (for API Platform deserialization)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function setSegment(string $segment): void
    {
        $this->segment = $segment;
    }

    public function setLoyaltyPoints(int $loyaltyPoints): void
    {
        $this->loyaltyPoints = $loyaltyPoints;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function setPreferences(string $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
    }

    public function getEmailBlindIndex(): ?string
    {
        return $this->emailBlindIndex;
    }

    public function setEmailBlindIndex(?string $emailBlindIndex): void
    {
        $this->emailBlindIndex = $emailBlindIndex;
    }
}
