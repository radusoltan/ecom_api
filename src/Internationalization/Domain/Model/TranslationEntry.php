<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Model;

use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * TranslationEntry Value Object
 *
 * Represents a single translation entry in the system.
 * This is a domain model, separate from Gedmo's Translation entity.
 *
 * Used for managing translations through the Translation Management API.
 */
final readonly class TranslationEntry
{
    public function __construct(
        public ?int $id,
        public TenantId $tenantId,
        public LanguageCode $locale,
        public TranslationDomain $domain,
        public TranslationKey $key,
        public TranslationValue $value,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public static function create(
        TenantId $tenantId,
        LanguageCode $locale,
        TranslationDomain $domain,
        TranslationKey $key,
        TranslationValue $value,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            locale: $locale,
            domain: $domain,
            key: $key,
            value: $value,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function updateValue(TranslationValue $newValue): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $newValue,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function isSameTranslation(
        LanguageCode $locale,
        TranslationDomain $domain,
        TranslationKey $key,
    ): bool {
        return $this->locale->equals($locale)
            && $this->domain->equals($domain)
            && $this->key->equals($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId->toString(),
            'locale' => $this->locale->value(),
            'domain' => $this->domain->value(),
            'key' => $this->key->value(),
            'value' => $this->value->value(),
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
