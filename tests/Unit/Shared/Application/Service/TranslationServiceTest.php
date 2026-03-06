<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Service;

use App\Shared\Application\Service\TranslationService;
use App\Shared\Domain\ValueObject\LanguageCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationService::class)]
final class TranslationServiceTest extends TestCase
{
    private TranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TranslationService();
    }

    // -----------------------------------------------------------------------
    // Initial state
    // -----------------------------------------------------------------------

    #[Test]
    public function itDefaultsToEnglish(): void
    {
        self::assertSame('en', $this->service->getCurrentLanguageCode());
    }

    #[Test]
    public function itReturnsDefaultLanguageAsLanguageCodeObject(): void
    {
        $lang = $this->service->getCurrentLanguage();

        self::assertInstanceOf(LanguageCode::class, $lang);
        self::assertTrue($lang->equals(LanguageCode::en()));
    }

    #[Test]
    public function itReturnsIsTrueForDefaultLanguage(): void
    {
        self::assertTrue($this->service->isDefaultLanguage());
    }

    // -----------------------------------------------------------------------
    // setCurrentLanguage / getCurrentLanguage
    // -----------------------------------------------------------------------

    #[Test]
    public function itChangesCurrentLanguage(): void
    {
        $this->service->setCurrentLanguage(LanguageCode::fr());

        self::assertSame('fr', $this->service->getCurrentLanguageCode());
    }

    #[Test]
    public function itReturnsNewLanguageObject(): void
    {
        $fr = LanguageCode::fr();
        $this->service->setCurrentLanguage($fr);

        self::assertTrue($this->service->getCurrentLanguage()->equals($fr));
    }

    #[Test]
    public function itIsNotDefaultLanguageAfterSwitch(): void
    {
        $this->service->setCurrentLanguage(LanguageCode::de());

        self::assertFalse($this->service->isDefaultLanguage());
    }

    #[Test]
    public function itIsDefaultLanguageAfterSwitchingBackToEn(): void
    {
        $this->service->setCurrentLanguage(LanguageCode::fr());
        $this->service->setCurrentLanguage(LanguageCode::en());

        self::assertTrue($this->service->isDefaultLanguage());
    }

    // -----------------------------------------------------------------------
    // getSupportedLanguages
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsSupportedLanguages(): void
    {
        $languages = $this->service->getSupportedLanguages();

        self::assertContains('en', $languages);
        self::assertContains('fr', $languages);
        self::assertContains('de', $languages);
        self::assertContains('ro', $languages);
        self::assertContains('es', $languages);
        self::assertContains('it', $languages);
    }

    #[Test]
    public function itReturnsSixSupportedLanguages(): void
    {
        self::assertCount(6, $this->service->getSupportedLanguages());
    }
}
