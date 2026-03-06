<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\TenantActivated;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\Event\TenantDeactivated;
use App\Tenant\Domain\Event\TenantReactivated;
use App\Tenant\Domain\Event\TenantSuspended;
use App\Tenant\Domain\Event\TenantUpdated;
use App\Tenant\Domain\Exception\TranslationQuotaExceededException;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantQuotas;
use App\Tenant\Domain\ValueObject\TenantStatus;

final class Tenant extends AggregateRoot
{
    /** @var array<LanguageCode> */
    private array $enabledLocales;

    /**
     * Business Rules:
     * - translation_quota_default: 10,000 translations per month
     * - translation_quota_min: 0
     * - translation_quota_max: 1,000,000
     */
    private const DEFAULT_TRANSLATION_QUOTA = 10000;

    private function __construct(
        private readonly TenantId $id,
        private TenantName $name,
        private Email $ownerEmail,
        private TenantStatus $status,
        private readonly \DateTimeImmutable $createdAt,
        private ?LanguageCode $defaultLocale = null,
        ?array $enabledLocales = null,
        private int $translationQuota = self::DEFAULT_TRANSLATION_QUOTA,
        private int $translationUsage = 0,
        private string $tier = 'starter',
    ) {
        $this->defaultLocale = $defaultLocale ?? LanguageCode::en();
        $this->enabledLocales = $enabledLocales ?? [LanguageCode::en()];
        $this->validateDefaultLocaleIsEnabled();
        $this->validateTranslationQuota();
        $this->validateTier();
        $this->recordEvent(new TenantCreated($this->id, $this->name, $this->ownerEmail));
    }

    public static function create(TenantName $name, Email $ownerEmail): self
    {
        return new self(
            TenantId::generate(),
            $name,
            $ownerEmail,
            TenantStatus::active(),
            new \DateTimeImmutable()
        );
    }

    public static function fromPersistence(
        TenantId $id,
        TenantName $name,
        Email $ownerEmail,
        TenantStatus $status,
        \DateTimeImmutable $createdAt,
        ?LanguageCode $defaultLocale = null,
        ?array $enabledLocales = null,
        int $translationQuota = self::DEFAULT_TRANSLATION_QUOTA,
        int $translationUsage = 0,
        string $tier = 'starter',
    ): self {
        $instance = new self(
            $id,
            $name,
            $ownerEmail,
            $status,
            $createdAt,
            $defaultLocale,
            $enabledLocales,
            $translationQuota,
            $translationUsage,
            $tier,
        );
        $instance->clearEvents();

        return $instance;
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            throw new \DomainException('Tenant is already active');
        }
        $this->status = TenantStatus::active();
        $this->recordEvent(new TenantActivated($this->id));
    }

    public function deactivate(): void
    {
        if (!$this->status->isActive()) {
            throw new \DomainException('Tenant is not active');
        }
        $this->status = TenantStatus::inactive();
        $this->recordEvent(new TenantDeactivated($this->id));
    }

    public function suspend(): void
    {
        if ($this->status->isSuspended()) {
            throw new \DomainException('Tenant is already suspended');
        }
        if ($this->status->isInactive()) {
            throw new \DomainException('Cannot suspend an inactive tenant');
        }
        $this->status = TenantStatus::suspended();
        $this->recordEvent(new TenantSuspended($this->id));
    }

    public function reactivate(): void
    {
        if ($this->status->isActive()) {
            throw new \DomainException('Tenant is already active');
        }
        if (!$this->status->isSuspended()) {
            throw new \DomainException('Only suspended tenants can be reactivated');
        }
        $this->status = TenantStatus::active();
        $this->recordEvent(new TenantReactivated($this->id));
    }

    public function update(TenantName $name, Email $ownerEmail): void
    {
        $this->name = $name;
        $this->ownerEmail = $ownerEmail;
        $this->recordEvent(new TenantUpdated($this->id, $this->name, $this->ownerEmail));
    }

    public function setDefaultLocale(LanguageCode $locale): void
    {
        if (!$this->isLocaleEnabled($locale)) {
            throw new \DomainException('Cannot set default locale to a disabled locale');
        }
        $this->defaultLocale = $locale;
    }

    public function enableLocale(LanguageCode $locale): void
    {
        if ($this->isLocaleEnabled($locale)) {
            throw new \DomainException('Locale is already enabled');
        }
        $this->enabledLocales[] = $locale;
    }

    public function disableLocale(LanguageCode $locale): void
    {
        if (!$this->isLocaleEnabled($locale)) {
            throw new \DomainException('Locale is not enabled');
        }
        if ($this->defaultLocale->equals($locale)) {
            throw new \DomainException('Cannot disable the default locale');
        }
        $this->enabledLocales = array_values(
            array_filter(
                $this->enabledLocales,
                fn (LanguageCode $l) => !$l->equals($locale)
            )
        );
    }

    private function isLocaleEnabled(LanguageCode $locale): bool
    {
        foreach ($this->enabledLocales as $enabledLocale) {
            if ($enabledLocale->equals($locale)) {
                return true;
            }
        }

        return false;
    }

    private function validateDefaultLocaleIsEnabled(): void
    {
        if (!$this->isLocaleEnabled($this->defaultLocale)) {
            throw new \DomainException('Default locale must be in enabled locales');
        }
    }

    private function validateTier(): void
    {
        if (!in_array($this->tier, ['starter', 'professional', 'enterprise'], true)) {
            throw new \DomainException(sprintf('Invalid tier: %s', $this->tier));
        }
    }

    private function validateTranslationQuota(): void
    {
        if ($this->translationQuota < 0) {
            throw new \DomainException('Translation quota cannot be negative');
        }
        if ($this->translationUsage < 0) {
            throw new \DomainException('Translation usage cannot be negative');
        }
        if ($this->translationUsage > $this->translationQuota) {
            throw new \DomainException('Translation usage cannot exceed quota');
        }
    }

    public function incrementTranslationUsage(int $count = 1): void
    {
        if ($count < 1) {
            throw new \DomainException('Translation usage increment must be positive');
        }

        $newUsage = $this->translationUsage + $count;

        if ($newUsage > $this->translationQuota) {
            throw TranslationQuotaExceededException::forTenant($this->id->toString(), $this->translationQuota, $this->translationUsage);
        }

        $this->translationUsage = $newUsage;
    }

    public function setTranslationQuota(int $quota): void
    {
        if ($quota < 0) {
            throw new \DomainException('Translation quota cannot be negative');
        }
        if ($quota > 1000000) {
            throw new \DomainException('Translation quota cannot exceed 1,000,000');
        }
        $this->translationQuota = $quota;
    }

    public function resetTranslationUsage(): void
    {
        $this->translationUsage = 0;
    }

    public function hasTranslationQuotaAvailable(int $count = 1): bool
    {
        return ($this->translationUsage + $count) <= $this->translationQuota;
    }

    public function remainingTranslationQuota(): int
    {
        return max(0, $this->translationQuota - $this->translationUsage);
    }

    public function upgradeTier(string $tier): void
    {
        $validTiers = ['starter', 'professional', 'enterprise'];
        if (!in_array($tier, $validTiers, true)) {
            throw new \DomainException(sprintf('Invalid tier: %s', $tier));
        }

        $currentIndex = array_search($this->tier, $validTiers, true);
        $newIndex = array_search($tier, $validTiers, true);

        if ($newIndex <= $currentIndex) {
            throw new \DomainException(sprintf('Cannot downgrade from %s to %s', $this->tier, $tier));
        }

        $this->tier = $tier;
    }

    public function quotas(): TenantQuotas
    {
        return TenantQuotas::forTier($this->tier);
    }

    public function tier(): string
    {
        return $this->tier;
    }

    // Getters
    public function id(): TenantId
    {
        return $this->id;
    }

    public function name(): TenantName
    {
        return $this->name;
    }

    public function ownerEmail(): Email
    {
        return $this->ownerEmail;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function defaultLocale(): LanguageCode
    {
        return $this->defaultLocale;
    }

    /**
     * @return array<LanguageCode>
     */
    public function enabledLocales(): array
    {
        return $this->enabledLocales;
    }

    public function translationQuota(): int
    {
        return $this->translationQuota;
    }

    public function translationUsage(): int
    {
        return $this->translationUsage;
    }
}
