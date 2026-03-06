<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidLanguageCodeException;
use App\Shared\Domain\ValueObject\LanguageCode;
use PHPUnit\Framework\TestCase;

final class LanguageCodeTest extends TestCase
{
    public function testFromStringCreatesValidLanguageCode(): void
    {
        $code = LanguageCode::fromString('en');

        $this->assertSame('en', $code->toString());
        $this->assertSame('en', $code->value());
        $this->assertSame('en', (string) $code);
    }

    public function testFromStringNormalizesCode(): void
    {
        $code = LanguageCode::fromString('  EN  ');

        $this->assertSame('en', $code->toString());
    }

    public function testFromStringConvertsToLowercase(): void
    {
        $code = LanguageCode::fromString('FR');

        $this->assertSame('fr', $code->toString());
    }

    public function testFromStringThrowsExceptionForUnsupportedLanguage(): void
    {
        $this->expectException(InvalidLanguageCodeException::class);

        LanguageCode::fromString('ja');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidLanguageCodeException::class);

        LanguageCode::fromString('');
    }

    public function testDefaultReturnsEnglish(): void
    {
        $code = LanguageCode::default();

        $this->assertSame('en', $code->toString());
        $this->assertTrue($code->isDefault());
    }

    public function testStaticFactoryMethods(): void
    {
        $en = LanguageCode::en();
        $fr = LanguageCode::fr();
        $de = LanguageCode::de();

        $this->assertSame('en', $en->toString());
        $this->assertSame('fr', $fr->toString());
        $this->assertSame('de', $de->toString());
    }

    public function testFromAcceptLanguageHeaderWithSimpleLanguage(): void
    {
        $code = LanguageCode::fromAcceptLanguageHeader('fr');

        $this->assertSame('fr', $code->toString());
    }

    public function testFromAcceptLanguageHeaderWithLocale(): void
    {
        $code = LanguageCode::fromAcceptLanguageHeader('fr-FR');

        $this->assertSame('fr', $code->toString());
    }

    public function testFromAcceptLanguageHeaderWithQualityValues(): void
    {
        $code = LanguageCode::fromAcceptLanguageHeader('fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7');

        $this->assertSame('fr', $code->toString());
    }

    public function testFromAcceptLanguageHeaderSelectsFirstSupported(): void
    {
        // Japanese is not supported, so it should select German
        $code = LanguageCode::fromAcceptLanguageHeader('ja-JP,ja;q=0.9,de-DE;q=0.8,de;q=0.7');

        $this->assertSame('de', $code->toString());
    }

    public function testFromAcceptLanguageHeaderFallsBackToDefault(): void
    {
        // Only unsupported languages
        $code = LanguageCode::fromAcceptLanguageHeader('ja-JP,ja;q=0.9,zh-CN;q=0.8');

        $this->assertSame('en', $code->toString());
        $this->assertTrue($code->isDefault());
    }

    public function testFromAcceptLanguageHeaderWithNull(): void
    {
        $code = LanguageCode::fromAcceptLanguageHeader(null);

        $this->assertSame('en', $code->toString());
        $this->assertTrue($code->isDefault());
    }

    public function testFromAcceptLanguageHeaderWithEmptyString(): void
    {
        $code = LanguageCode::fromAcceptLanguageHeader('');

        $this->assertSame('en', $code->toString());
        $this->assertTrue($code->isDefault());
    }

    public function testEqualsReturnsTrueForSameCode(): void
    {
        $code1 = LanguageCode::fromString('fr');
        $code2 = LanguageCode::fromString('fr');

        $this->assertTrue($code1->equals($code2));
    }

    public function testEqualsReturnsFalseForDifferentCode(): void
    {
        $code1 = LanguageCode::fromString('fr');
        $code2 = LanguageCode::fromString('de');

        $this->assertFalse($code1->equals($code2));
    }

    public function testIsDefaultReturnsTrueForEnglish(): void
    {
        $code = LanguageCode::en();

        $this->assertTrue($code->isDefault());
    }

    public function testIsDefaultReturnsFalseForNonEnglish(): void
    {
        $code = LanguageCode::fr();

        $this->assertFalse($code->isDefault());
    }

    public function testSupportedLanguagesReturnsCorrectArray(): void
    {
        $languages = LanguageCode::supportedLanguages();

        $this->assertSame(['en', 'fr', 'de', 'ro', 'es', 'it'], $languages);
    }

    public function testAllReturnsCorrectArray(): void
    {
        $languages = LanguageCode::all();

        $this->assertSame(['en', 'fr', 'de', 'ro', 'es', 'it'], $languages);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('complexAcceptLanguageHeaderProvider')]
    public function testFromAcceptLanguageHeaderWithComplexHeaders(string $header, string $expectedCode): void
    {
        $code = LanguageCode::fromAcceptLanguageHeader($header);

        $this->assertSame($expectedCode, $code->toString());
    }

    public static function complexAcceptLanguageHeaderProvider(): array
    {
        return [
            'Chrome French' => ['fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7', 'fr'],
            'Chrome German' => ['de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7', 'de'],
            'Firefox English' => ['en-US,en;q=0.5', 'en'],
            'Safari Mixed' => ['de-CH,de;q=0.9,fr-CH;q=0.8,fr;q=0.7,en;q=0.6', 'de'],
            'Opera Fallback' => ['ja-JP,ja;q=0.9,zh-CN;q=0.8', 'en'], // No supported language
            'Edge German Primary' => ['de,en-US;q=0.9,en;q=0.8', 'de'],
            'Mobile Browser' => ['fr-CA,fr;q=0.9,en-CA;q=0.8,en;q=0.7', 'fr'],
        ];
    }
}
