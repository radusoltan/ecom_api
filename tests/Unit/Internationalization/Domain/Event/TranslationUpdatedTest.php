<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Event;

use App\Internationalization\Domain\Event\TranslationUpdated;
use App\Internationalization\Domain\Model\TranslationId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationUpdated::class)]
final class TranslationUpdatedTest extends TestCase
{
    private TranslationId $translationId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->translationId = TranslationId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------
    // Construction
    // -------

    #[Test]
    public function itStoresTranslationId(): void
    {
        $event = new TranslationUpdated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'greeting.hello',
            locale: 'en',
        );

        self::assertTrue($this->translationId->equals($event->translationId));
    }

    #[Test]
    public function itStoresTenantId(): void
    {
        $event = new TranslationUpdated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'greeting.hello',
            locale: 'en',
        );

        self::assertTrue($this->tenantId->equals($event->tenantId));
    }

    #[Test]
    public function itStoresDomain(): void
    {
        $event = new TranslationUpdated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'emails',
            key: 'subject.welcome',
            locale: 'fr',
        );

        self::assertSame('emails', $event->domain);
    }

    #[Test]
    public function itStoresKey(): void
    {
        $event = new TranslationUpdated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'nav.home',
            locale: 'de',
        );

        self::assertSame('nav.home', $event->key);
    }

    #[Test]
    public function itStoresLocale(): void
    {
        $event = new TranslationUpdated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'greeting.hello',
            locale: 'ro',
        );

        self::assertSame('ro', $event->locale);
    }

    #[Test]
    public function differentTranslationIdsProduceDifferentEvents(): void
    {
        $otherId = TranslationId::generate();

        $event1 = new TranslationUpdated($this->translationId, $this->tenantId, 'messages', 'key', 'en');
        $event2 = new TranslationUpdated($otherId, $this->tenantId, 'messages', 'key', 'en');

        self::assertFalse($event1->translationId->equals($event2->translationId));
    }
}
