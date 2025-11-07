<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Persistence\Doctrine\Entity;

use App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\Translation;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantStatus;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
#[ORM\Index(columns: ['owner_email'], name: 'idx_tenants_owner_email')]
#[ORM\Index(columns: ['status'], name: 'idx_tenants_status')]
#[Gedmo\TranslationEntity(class: Translation::class)]
class TenantEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 36)]
        private string $id,
        #[ORM\Column(type: 'string', length: 100)]
        #[Gedmo\Translatable]
        private string $name,
        #[ORM\Column(type: 'string', length: 255, unique: true)]
        private string $ownerEmail,
        #[ORM\Column(type: 'string', length: 20)]
        private string $status,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'string', length: 2)]
        private string $defaultLocale = 'en',
        #[ORM\Column(type: 'json')]
        private array $enabledLocales = ['en'],
        #[ORM\Column(type: 'integer', options: ['default' => 10000])]
        private int $translationQuota = 10000,
        #[ORM\Column(type: 'integer', options: ['default' => 0])]
        private int $translationUsage = 0
    ) {
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private string $slug;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function setTranslatableLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getOwnerEmail(): string
    {
        return $this->ownerEmail;
    }

    public function setOwnerEmail(string $ownerEmail): void
    {
        $this->ownerEmail = $ownerEmail;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(string $defaultLocale): void
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    public function setEnabledLocales(array $enabledLocales): void
    {
        $this->enabledLocales = $enabledLocales;
    }

    public function getTranslationQuota(): int
    {
        return $this->translationQuota;
    }

    public function setTranslationQuota(int $translationQuota): void
    {
        $this->translationQuota = $translationQuota;
    }

    public function getTranslationUsage(): int
    {
        return $this->translationUsage;
    }

    public function setTranslationUsage(int $translationUsage): void
    {
        $this->translationUsage = $translationUsage;
    }

    /**
     * Convert Doctrine entity to Domain aggregate.
     */
    public function toDomain(): Tenant
    {
        $enabledLocales = array_map(
            fn (string $code) => LanguageCode::fromString($code),
            $this->enabledLocales
        );

        return Tenant::fromPersistence(
            TenantId::fromString($this->id),
            TenantName::fromString($this->name),
            Email::fromString($this->ownerEmail),
            TenantStatus::fromString($this->status),
            $this->createdAt,
            LanguageCode::fromString($this->defaultLocale),
            $enabledLocales,
            $this->translationQuota,
            $this->translationUsage
        );
    }

    /**
     * Convert Domain aggregate to Doctrine entity.
     */
    public static function fromDomain(Tenant $tenant): self
    {
        $enabledLocales = array_map(
            fn (LanguageCode $locale) => $locale->value(),
            $tenant->enabledLocales()
        );

        return new self(
            $tenant->id()->toString(),
            $tenant->name()->value(),
            $tenant->ownerEmail()->value(),
            $tenant->status()->value(),
            $tenant->createdAt(),
            $tenant->defaultLocale()->value(),
            $enabledLocales,
            $tenant->translationQuota(),
            $tenant->translationUsage()
        );
    }

    /**
     * Update entity from Domain aggregate (for updates).
     */
    public function updateFromDomain(Tenant $tenant): void
    {
        $this->name = $tenant->name()->value();
        $this->ownerEmail = $tenant->ownerEmail()->value();
        $this->status = $tenant->status()->value();
        $this->defaultLocale = $tenant->defaultLocale()->value();
        $this->enabledLocales = array_map(
            fn (LanguageCode $locale) => $locale->value(),
            $tenant->enabledLocales()
        );
        $this->translationQuota = $tenant->translationQuota();
        $this->translationUsage = $tenant->translationUsage();
    }
}
