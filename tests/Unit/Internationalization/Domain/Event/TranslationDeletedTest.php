<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Event;

use App\Internationalization\Domain\Event\TranslationDeleted;
use App\Internationalization\Domain\Model\TranslationId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationDeleted::class)]
final class TranslationDeletedTest extends TestCase
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
        $event = new TranslationDeleted(
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
        $event = new TranslationDeleted(
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
        $event = new TranslationDeleted(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'admin',
            key: 'header.title',
            locale: 'de',
        );

        self::assertSame('admin', $event->domain);
    }

    #[Test]
    public function itStoresKey(): void
    {
        $event = new TranslationDeleted(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'error.not_found',
            locale: 'en',
        );

        self::assertSame('error.not_found', $event->key);
    }

    #[Test]
    public function itStoresLocale(): void
    {
        $event = new TranslationDeleted(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'greeting.hello',
            locale: 'it',
        );

        self::assertSame('it', $event->locale);
    }
}
