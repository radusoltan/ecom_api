<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Event;

use App\Internationalization\Domain\Event\TranslationCreated;
use App\Internationalization\Domain\Model\TranslationId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationCreated::class)]
final class TranslationCreatedTest extends TestCase
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
        $event = new TranslationCreated(
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
        $event = new TranslationCreated(
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
        $event = new TranslationCreated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'validators',
            key: 'email.invalid',
            locale: 'fr',
        );

        self::assertSame('validators', $event->domain);
    }

    #[Test]
    public function itStoresKey(): void
    {
        $event = new TranslationCreated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'button.save',
            locale: 'de',
        );

        self::assertSame('button.save', $event->key);
    }

    #[Test]
    public function itStoresLocale(): void
    {
        $event = new TranslationCreated(
            translationId: $this->translationId,
            tenantId: $this->tenantId,
            domain: 'messages',
            key: 'greeting.hello',
            locale: 'ro',
        );

        self::assertSame('ro', $event->locale);
    }
}
