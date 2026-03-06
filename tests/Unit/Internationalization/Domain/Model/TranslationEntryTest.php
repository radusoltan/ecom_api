<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Model;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TranslationEntryTest extends TestCase
{
    private TenantId $tenantId;
    private LanguageCode $locale;
    private TranslationDomain $domain;
    private TranslationKey $key;
    private TranslationValue $value;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->locale = LanguageCode::en();
        $this->domain = TranslationDomain::fromString('messages');
        $this->key = TranslationKey::fromString('greeting.hello');
        $this->value = TranslationValue::fromString('Hello, World!');
    }

    // --- create() ---

    public function testCreateSetsNullId(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertNull($entry->id);
    }

    public function testCreateSetsTenantId(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('00000000-0000-4000-8000-000000000001', $entry->tenantId->toString());
    }

    public function testCreateSetsLocale(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('en', $entry->locale->value());
    }

    public function testCreateSetsDomain(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('messages', $entry->domain->value());
    }

    public function testCreateSetsKey(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('greeting.hello', $entry->key->value());
    }

    public function testCreateSetsValue(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('Hello, World!', $entry->value->value());
    }

    public function testCreateSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );
        $after = new \DateTimeImmutable();

        self::assertNotNull($entry->createdAt);
        self::assertGreaterThanOrEqual($before, $entry->createdAt);
        self::assertLessThanOrEqual($after, $entry->createdAt);
    }

    public function testCreateSetsUpdatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );
        $after = new \DateTimeImmutable();

        self::assertNotNull($entry->updatedAt);
        self::assertGreaterThanOrEqual($before, $entry->updatedAt);
        self::assertLessThanOrEqual($after, $entry->updatedAt);
    }

    // --- Constructor with explicit id ---

    public function testConstructorAcceptsExplicitIntId(): void
    {
        $entry = new TranslationEntry(
            id: 42,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame(42, $entry->id);
    }

    public function testConstructorAcceptsNullTimestamps(): void
    {
        $entry = new TranslationEntry(
            id: null,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertNull($entry->createdAt);
        self::assertNull($entry->updatedAt);
    }

    // --- updateValue() ---

    public function testUpdateValueReturnsNewInstance(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $newValue = TranslationValue::fromString('Updated value');
        $updated = $entry->updateValue($newValue);

        self::assertNotSame($entry, $updated);
    }

    public function testUpdateValueSetsNewValue(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $newValue = TranslationValue::fromString('Updated value');
        $updated = $entry->updateValue($newValue);

        self::assertSame('Updated value', $updated->value->value());
    }

    public function testUpdateValuePreservesOriginalValue(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $entry->updateValue(TranslationValue::fromString('Updated'));

        self::assertSame('Hello, World!', $entry->value->value());
    }

    public function testUpdateValuePreservesId(): void
    {
        $entry = new TranslationEntry(
            id: 99,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $updated = $entry->updateValue(TranslationValue::fromString('New'));

        self::assertSame(99, $updated->id);
    }

    public function testUpdateValuePreservesTenantId(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $updated = $entry->updateValue(TranslationValue::fromString('New'));

        self::assertSame('00000000-0000-4000-8000-000000000001', $updated->tenantId->toString());
    }

    public function testUpdateValuePreservesLocale(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $updated = $entry->updateValue(TranslationValue::fromString('New'));

        self::assertSame('en', $updated->locale->value());
    }

    public function testUpdateValuePreservesDomain(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $updated = $entry->updateValue(TranslationValue::fromString('New'));

        self::assertSame('messages', $updated->domain->value());
    }

    public function testUpdateValuePreservesKey(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $updated = $entry->updateValue(TranslationValue::fromString('New'));

        self::assertSame('greeting.hello', $updated->key->value());
    }

    public function testUpdateValuePreservesCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $entry = new TranslationEntry(
            id: null,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );

        $updated = $entry->updateValue(TranslationValue::fromString('New'));

        self::assertSame($createdAt, $updated->createdAt);
    }

    public function testUpdateValueSetsNewUpdatedAt(): void
    {
        $past = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $entry = new TranslationEntry(
            id: null,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
            createdAt: $past,
            updatedAt: $past,
        );

        $before = new \DateTimeImmutable();
        $updated = $entry->updateValue(TranslationValue::fromString('New'));
        $after = new \DateTimeImmutable();

        self::assertNotNull($updated->updatedAt);
        self::assertGreaterThanOrEqual($before, $updated->updatedAt);
        self::assertLessThanOrEqual($after, $updated->updatedAt);
    }

    // --- isSameTranslation() ---

    public function testIsSameTranslationReturnsTrueForMatchingFields(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertTrue($entry->isSameTranslation(
            LanguageCode::en(),
            TranslationDomain::fromString('messages'),
            TranslationKey::fromString('greeting.hello'),
        ));
    }

    public function testIsSameTranslationReturnsFalseWhenLocaleDiffers(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertFalse($entry->isSameTranslation(
            LanguageCode::fr(),
            TranslationDomain::fromString('messages'),
            TranslationKey::fromString('greeting.hello'),
        ));
    }

    public function testIsSameTranslationReturnsFalseWhenDomainDiffers(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertFalse($entry->isSameTranslation(
            LanguageCode::en(),
            TranslationDomain::fromString('validators'),
            TranslationKey::fromString('greeting.hello'),
        ));
    }

    public function testIsSameTranslationReturnsFalseWhenKeyDiffers(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertFalse($entry->isSameTranslation(
            LanguageCode::en(),
            TranslationDomain::fromString('messages'),
            TranslationKey::fromString('greeting.goodbye'),
        ));
    }

    public function testIsSameTranslationReturnsFalseWhenAllFieldsDiffer(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertFalse($entry->isSameTranslation(
            LanguageCode::de(),
            TranslationDomain::fromString('emails'),
            TranslationKey::fromString('subject.welcome'),
        ));
    }

    // --- toArray() ---

    public function testToArrayContainsAllExpectedKeys(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $array = $entry->toArray();

        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('tenantId', $array);
        self::assertArrayHasKey('locale', $array);
        self::assertArrayHasKey('domain', $array);
        self::assertArrayHasKey('key', $array);
        self::assertArrayHasKey('value', $array);
        self::assertArrayHasKey('createdAt', $array);
        self::assertArrayHasKey('updatedAt', $array);
    }

    public function testToArrayReturnsNullId(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertNull($entry->toArray()['id']);
    }

    public function testToArrayReturnsIntId(): void
    {
        $entry = new TranslationEntry(
            id: 7,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame(7, $entry->toArray()['id']);
    }

    public function testToArrayReturnsTenantIdAsString(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('00000000-0000-4000-8000-000000000001', $entry->toArray()['tenantId']);
    }

    public function testToArrayReturnsLocaleAsString(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('en', $entry->toArray()['locale']);
    }

    public function testToArrayReturnsDomainAsString(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('messages', $entry->toArray()['domain']);
    }

    public function testToArrayReturnsKeyAsString(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('greeting.hello', $entry->toArray()['key']);
    }

    public function testToArrayReturnsValueAsString(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        self::assertSame('Hello, World!', $entry->toArray()['value']);
    }

    public function testToArrayReturnsCreatedAtAsAtomFormat(): void
    {
        $fixedTime = new \DateTimeImmutable('2025-06-15T12:00:00+00:00');
        $entry = new TranslationEntry(
            id: null,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
            createdAt: $fixedTime,
            updatedAt: $fixedTime,
        );

        self::assertSame('2025-06-15T12:00:00+00:00', $entry->toArray()['createdAt']);
    }

    public function testToArrayReturnsUpdatedAtAsAtomFormat(): void
    {
        $fixedTime = new \DateTimeImmutable('2025-06-15T12:00:00+00:00');
        $entry = new TranslationEntry(
            id: null,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
            createdAt: $fixedTime,
            updatedAt: $fixedTime,
        );

        self::assertSame('2025-06-15T12:00:00+00:00', $entry->toArray()['updatedAt']);
    }

    public function testToArrayReturnsNullForNullTimestamps(): void
    {
        $entry = new TranslationEntry(
            id: null,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
            createdAt: null,
            updatedAt: null,
        );

        $array = $entry->toArray();
        self::assertNull($array['createdAt']);
        self::assertNull($array['updatedAt']);
    }

    public function testToArrayAfterUpdateValueReflectsNewValue(): void
    {
        $entry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );

        $updated = $entry->updateValue(TranslationValue::fromString('Bonjour!'));

        self::assertSame('Bonjour!', $updated->toArray()['value']);
    }
}
