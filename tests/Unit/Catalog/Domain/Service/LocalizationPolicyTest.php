<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Service;

use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use PHPUnit\Framework\TestCase;

final class LocalizationPolicyTest extends TestCase
{
    private LocalizationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new LocalizationPolicy();
    }

    public function testBuildFallbackChainWithFullLocale(): void
    {
        $requestedLocale = Locale::fromString('ro_RO');
        $tenantDefault = Locale::fromString('fr_FR');

        $chain = $this->policy->buildFallbackChain($requestedLocale, $tenantDefault);

        // Expected chain: ro_RO -> ro -> fr_FR -> fr -> en_US -> en
        $this->assertCount(6, $chain);
        $this->assertEquals('ro_RO', $chain[0]->toString());
        $this->assertEquals('ro', $chain[1]->toString());
        $this->assertEquals('fr_FR', $chain[2]->toString());
        $this->assertEquals('fr', $chain[3]->toString());
        $this->assertEquals('en_US', $chain[4]->toString());
        $this->assertEquals('en', $chain[5]->toString());
    }

    public function testBuildFallbackChainWithBaseLocale(): void
    {
        $requestedLocale = Locale::fromString('de');
        $tenantDefault = null;

        $chain = $this->policy->buildFallbackChain($requestedLocale, $tenantDefault);

        // Expected chain: de -> en_US -> en
        $this->assertCount(3, $chain);
        $this->assertEquals('de', $chain[0]->toString());
        $this->assertEquals('en_US', $chain[1]->toString());
        $this->assertEquals('en', $chain[2]->toString());
    }

    public function testBuildFallbackChainAvoidsDuplicates(): void
    {
        $requestedLocale = Locale::fromString('en_US');
        $tenantDefault = Locale::fromString('en_US');

        $chain = $this->policy->buildFallbackChain($requestedLocale, $tenantDefault);

        // Should not have duplicates
        $this->assertCount(2, $chain);
        $this->assertEquals('en_US', $chain[0]->toString());
        $this->assertEquals('en', $chain[1]->toString());
    }

    public function testResolveStringWithExactMatch(): void
    {
        $localizedString = LocalizedString::fromArray([
            'ro_RO' => 'Text în română',
            'en_US' => 'Text in English',
            'fr_FR' => 'Texte en français'
        ]);

        $requestedLocale = Locale::fromString('ro_RO');
        $result = $this->policy->resolveString($localizedString, $requestedLocale);

        $this->assertEquals('Text în română', $result);
    }

    public function testResolveStringWithFallbackToBase(): void
    {
        $localizedString = LocalizedString::fromArray([
            'ro' => 'Text în română',
            'en_US' => 'Text in English'
        ]);

        $requestedLocale = Locale::fromString('ro_RO'); // Request ro_RO
        $result = $this->policy->resolveString($localizedString, $requestedLocale);

        // Should fall back to 'ro'
        $this->assertEquals('Text în română', $result);
    }

    public function testResolveStringWithFallbackToTenantDefault(): void
    {
        $localizedString = LocalizedString::fromArray([
            'fr_FR' => 'Texte en français',
            'en_US' => 'Text in English'
        ]);

        $requestedLocale = Locale::fromString('ro_RO');
        $tenantDefault = Locale::fromString('fr_FR');

        $result = $this->policy->resolveString($localizedString, $requestedLocale, $tenantDefault);

        // Should fall back to tenant default
        $this->assertEquals('Texte en français', $result);
    }

    public function testResolveStringWithFallbackToSystemDefault(): void
    {
        $localizedString = LocalizedString::fromArray([
            'en_US' => 'Text in English'
        ]);

        $requestedLocale = Locale::fromString('ro_RO');
        $result = $this->policy->resolveString($localizedString, $requestedLocale);

        // Should fall back to system default (en_US)
        $this->assertEquals('Text in English', $result);
    }

    public function testResolveStringWithEmptyLocalizedString(): void
    {
        $localizedString = LocalizedString::empty();
        $requestedLocale = Locale::fromString('ro_RO');

        $result = $this->policy->resolveString($localizedString, $requestedLocale);

        $this->assertNull($result);
    }

    public function testGetFallbackDepth(): void
    {
        $localizedString = LocalizedString::fromArray([
            'fr_FR' => 'Texte en français',
            'en_US' => 'Text in English'
        ]);

        $requestedLocale = Locale::fromString('ro_RO');
        $tenantDefault = Locale::fromString('fr_FR');

        $depth = $this->policy->getFallbackDepth($localizedString, $requestedLocale, $tenantDefault);

        // Found at position 2 (ro_RO -> ro -> fr_FR)
        $this->assertEquals(2, $depth);
    }

    public function testGetResolvedLocale(): void
    {
        $localizedString = LocalizedString::fromArray([
            'fr' => 'Texte en français',
            'en_US' => 'Text in English'
        ]);

        $requestedLocale = Locale::fromString('fr_FR');
        $resolvedLocale = $this->policy->getResolvedLocale($localizedString, $requestedLocale);

        $this->assertNotNull($resolvedLocale);
        $this->assertEquals('fr', $resolvedLocale->toString());
    }

    public function testParseAcceptLanguageHeader(): void
    {
        $header = 'ro-RO, ro;q=0.8, en-US;q=0.6, en;q=0.4';
        $locales = LocalizationPolicy::parseAcceptLanguageHeader($header);

        $this->assertCount(4, $locales);
        $this->assertEquals('ro_RO', $locales[0]->toString());
        $this->assertEquals('ro', $locales[1]->toString());
        $this->assertEquals('en_US', $locales[2]->toString());
        $this->assertEquals('en', $locales[3]->toString());
    }

    public function testParseAcceptLanguageHeaderWithQualityWeights(): void
    {
        $header = 'en-US;q=0.5, fr-FR;q=0.9, de;q=0.7';
        $locales = LocalizationPolicy::parseAcceptLanguageHeader($header);

        // Should be ordered by quality: fr-FR (0.9), de (0.7), en-US (0.5)
        $this->assertCount(3, $locales);
        $this->assertEquals('fr_FR', $locales[0]->toString());
        $this->assertEquals('de', $locales[1]->toString());
        $this->assertEquals('en_US', $locales[2]->toString());
    }

    public function testParseAcceptLanguageHeaderWithInvalidLocales(): void
    {
        $header = 'invalid, en-US, x-unknown;q=0.5, fr-FR';
        $locales = LocalizationPolicy::parseAcceptLanguageHeader($header);

        // Should only return valid locales
        $this->assertCount(2, $locales);
        $this->assertEquals('en_US', $locales[0]->toString());
        $this->assertEquals('fr_FR', $locales[1]->toString());
    }

    public function testNegotiateLocaleWithSupportedLocale(): void
    {
        $supportedLocales = [
            Locale::fromString('en_US'),
            Locale::fromString('fr_FR'),
            Locale::fromString('de_DE')
        ];
        $policy = new LocalizationPolicy($supportedLocales);

        $header = 'ro-RO, fr-FR;q=0.8, en-US;q=0.6';
        $negotiated = $policy->negotiateLocale($header);

        // Should return fr_FR as first supported locale
        $this->assertEquals('fr_FR', $negotiated->toString());
    }

    public function testNegotiateLocaleWithNoSupportedLocales(): void
    {
        $supportedLocales = [
            Locale::fromString('ja_JP'),
            Locale::fromString('zh_CN')
        ];
        $policy = new LocalizationPolicy($supportedLocales);

        $header = 'ro-RO, fr-FR;q=0.8, en-US;q=0.6';
        $defaultLocale = Locale::fromString('ja_JP');
        $negotiated = $policy->negotiateLocale($header, $defaultLocale);

        // Should return the provided default
        $this->assertEquals('ja_JP', $negotiated->toString());
    }

    public function testResolveMultipleStrings(): void
    {
        $strings = [
            'name' => LocalizedString::fromArray([
                'ro_RO' => 'Nume',
                'en_US' => 'Name'
            ]),
            'description' => LocalizedString::fromArray([
                'ro_RO' => 'Descriere',
                'en_US' => 'Description'
            ])
        ];

        $requestedLocale = Locale::fromString('ro_RO');
        $resolved = $this->policy->resolveMultiple($strings, $requestedLocale);

        $this->assertEquals('Nume', $resolved['name']);
        $this->assertEquals('Descriere', $resolved['description']);
    }
}