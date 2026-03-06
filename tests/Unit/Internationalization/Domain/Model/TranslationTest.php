<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Model;

use App\Internationalization\Domain\Event\TranslationCreated;
use App\Internationalization\Domain\Event\TranslationDeleted;
use App\Internationalization\Domain\Event\TranslationUpdated;
use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TranslationTest extends TestCase
{
    private TranslationId $id;
    private TenantId $tenantId;
    private TranslationDomain $domain;
    private TranslationKey $key;
    private LanguageCode $locale;
    private TranslationValue $value;

    protected function setUp(): void
    {
        $this->id = TranslationId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->domain = TranslationDomain::fromString('messages');
        $this->key = TranslationKey::fromString('greeting.hello');
        $this->locale = LanguageCode::en();
        $this->value = TranslationValue::fromString('Hello, World!');
    }

    public function testCreateRecordsTranslationCreatedEvent(): void
    {
        $translation = Translation::create(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
        );

        $events = $translation->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TranslationCreated::class, $events[0]);
        self::assertTrue($events[0]->translationId->equals($this->id));
        self::assertSame('messages', $events[0]->domain);
        self::assertSame('greeting.hello', $events[0]->key);
        self::assertSame('en', $events[0]->locale);
    }

    public function testCreateSetsAllProperties(): void
    {
        $translation = Translation::create(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
            parameters: ['name' => 'user'],
        );

        self::assertTrue($translation->id()->equals($this->id));
        self::assertSame($this->tenantId->toString(), $translation->tenantId()->toString());
        self::assertSame('messages', $translation->domain()->value());
        self::assertSame('greeting.hello', $translation->key()->value());
        self::assertSame('en', $translation->locale()->value());
        self::assertSame('Hello, World!', $translation->value()->value());
        self::assertSame(['name' => 'user'], $translation->parameters());
        self::assertInstanceOf(\DateTimeImmutable::class, $translation->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $translation->updatedAt());
    }

    public function testCreateWithEmptyParametersDefault(): void
    {
        $translation = Translation::create(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
        );

        self::assertSame([], $translation->parameters());
    }

    public function testUpdateValueChangesValueAndRecordsEvent(): void
    {
        $translation = Translation::create(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
        );
        $translation->popEvents(); // Clear create event

        $newValue = TranslationValue::fromString('Hello, Updated!');
        $translation->updateValue($newValue, ['count' => '5']);

        self::assertSame('Hello, Updated!', $translation->value()->value());
        self::assertSame(['count' => '5'], $translation->parameters());

        $events = $translation->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TranslationUpdated::class, $events[0]);
        self::assertTrue($events[0]->translationId->equals($this->id));
    }

    public function testUpdateValueUpdatesTimestamp(): void
    {
        $translation = Translation::create(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
        );

        $originalUpdatedAt = $translation->updatedAt();
        usleep(1000); // Ensure time difference

        $translation->updateValue(TranslationValue::fromString('Updated'));

        self::assertGreaterThanOrEqual($originalUpdatedAt, $translation->updatedAt());
    }

    public function testDeleteRecordsTranslationDeletedEvent(): void
    {
        $translation = Translation::create(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
        );
        $translation->popEvents(); // Clear create event

        $translation->delete();

        $events = $translation->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TranslationDeleted::class, $events[0]);
        self::assertTrue($events[0]->translationId->equals($this->id));
        self::assertSame('messages', $events[0]->domain);
        self::assertSame('greeting.hello', $events[0]->key);
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $now = new \DateTimeImmutable();
        $translation = Translation::reconstitute(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
            parameters: ['name' => 'test'],
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame([], $translation->popEvents());
        self::assertSame(['name' => 'test'], $translation->parameters());
    }

    public function testToEntryCreatesLegacyValueObject(): void
    {
        $translation = Translation::create(
            id: $this->id,
            tenantId: $this->tenantId,
            domain: $this->domain,
            key: $this->key,
            locale: $this->locale,
            value: $this->value,
        );

        $entry = $translation->toEntry(42);

        self::assertSame(42, $entry->id);
        self::assertSame($this->tenantId->toString(), $entry->tenantId->toString());
        self::assertSame('en', $entry->locale->value());
        self::assertSame('messages', $entry->domain->value());
        self::assertSame('greeting.hello', $entry->key->value());
        self::assertSame('Hello, World!', $entry->value->value());
    }
}
