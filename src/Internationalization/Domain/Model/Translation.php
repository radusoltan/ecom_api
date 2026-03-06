<?php

declare(strict_types=1);

namespace App\Internationalization\Domain\Model;

use App\Internationalization\Domain\Event\TranslationCreated;
use App\Internationalization\Domain\Event\TranslationDeleted;
use App\Internationalization\Domain\Event\TranslationUpdated;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Translation Aggregate Root per PRD §4.7.
 *
 * Represents a tenant-scoped translation entry with UUID identity,
 * domain events, and placeholder parameter support.
 */
final class Translation extends AggregateRoot
{
    private TranslationId $id;
    private TenantId $tenantId;
    private TranslationDomain $domain;
    private TranslationKey $key;
    private LanguageCode $locale;
    private TranslationValue $value;
    /** @var array<string, string> */
    private array $parameters;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, string> $parameters
     */
    private function __construct(
        TranslationId $id,
        TenantId $tenantId,
        TranslationDomain $domain,
        TranslationKey $key,
        LanguageCode $locale,
        TranslationValue $value,
        array $parameters,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->domain = $domain;
        $this->key = $key;
        $this->locale = $locale;
        $this->value = $value;
        $this->parameters = $parameters;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function create(
        TranslationId $id,
        TenantId $tenantId,
        TranslationDomain $domain,
        TranslationKey $key,
        LanguageCode $locale,
        TranslationValue $value,
        array $parameters = [],
    ): self {
        $now = new \DateTimeImmutable();
        $translation = new self($id, $tenantId, $domain, $key, $locale, $value, $parameters, $now, $now);

        $translation->recordEvent(new TranslationCreated(
            $id,
            $tenantId,
            $domain->value(),
            $key->value(),
            $locale->value(),
        ));

        return $translation;
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function reconstitute(
        TranslationId $id,
        TenantId $tenantId,
        TranslationDomain $domain,
        TranslationKey $key,
        LanguageCode $locale,
        TranslationValue $value,
        array $parameters,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $tenantId, $domain, $key, $locale, $value, $parameters, $createdAt, $updatedAt);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function updateValue(TranslationValue $newValue, array $parameters = []): void
    {
        $this->value = $newValue;
        $this->parameters = $parameters;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TranslationUpdated(
            $this->id,
            $this->tenantId,
            $this->domain->value(),
            $this->key->value(),
            $this->locale->value(),
        ));
    }

    public function delete(): void
    {
        $this->recordEvent(new TranslationDeleted(
            $this->id,
            $this->tenantId,
            $this->domain->value(),
            $this->key->value(),
            $this->locale->value(),
        ));
    }

    public function id(): TranslationId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function domain(): TranslationDomain
    {
        return $this->domain;
    }

    public function key(): TranslationKey
    {
        return $this->key;
    }

    public function locale(): LanguageCode
    {
        return $this->locale;
    }

    public function value(): TranslationValue
    {
        return $this->value;
    }

    /**
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Convert to the legacy TranslationEntry value object for backward compatibility.
     */
    public function toEntry(?int $legacyId = null): TranslationEntry
    {
        return new TranslationEntry(
            id: $legacyId,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }
}
