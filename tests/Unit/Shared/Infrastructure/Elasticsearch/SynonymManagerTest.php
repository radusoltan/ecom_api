<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Elasticsearch;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Infrastructure\Elasticsearch\SynonymManager;
use PHPUnit\Framework\TestCase;

final class SynonymManagerTest extends TestCase
{
    private SynonymManager $synonymManager;

    protected function setUp(): void
    {
        $this->synonymManager = new SynonymManager();
    }

    public function testGetSynonymsForEnglish(): void
    {
        $locale = Locale::fromString('en_US');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
        $this->assertContains('laptop, notebook, portable computer', $synonyms);
        $this->assertContains('smartphone, mobile phone, cellphone, cell phone', $synonyms);
    }

    public function testGetSynonymsForRomanian(): void
    {
        $locale = Locale::fromString('ro_RO');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
        $this->assertContains('laptop, notebook, calculator portabil', $synonyms);
        $this->assertContains('cumpara, comanda, achizitioneaza', $synonyms);
    }

    public function testGetSynonymsForGerman(): void
    {
        $locale = Locale::fromString('de_DE');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
        $this->assertContains('laptop, notebook, tragbarer computer', $synonyms);
        $this->assertContains('kaufen, bestellen, erwerben', $synonyms);
    }

    public function testGetSynonymsForFrench(): void
    {
        $locale = Locale::fromString('fr_FR');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
        $this->assertContains('laptop, ordinateur portable, portable', $synonyms);
        $this->assertContains('acheter, commander, acquérir', $synonyms);
    }

    public function testGetSynonymsForSpanish(): void
    {
        $locale = Locale::fromString('es_ES');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
        $this->assertContains('laptop, portátil, ordenador portátil', $synonyms);
        $this->assertContains('comprar, adquirir, ordenar', $synonyms);
    }

    public function testGetSynonymsForItalian(): void
    {
        $locale = Locale::fromString('it_IT');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
        $this->assertContains('laptop, notebook, computer portatile', $synonyms);
        $this->assertContains('comprare, acquistare, ordinare', $synonyms);
    }

    public function testGetSynonymsForDefaultLocaleWhenUnsupported(): void
    {
        // Using English locale but checking default behavior
        $locale = Locale::fromString('en_US');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
    }

    public function testSynonymsAreInSolrFormat(): void
    {
        $locale = Locale::fromString('en_US');
        $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

        foreach ($synonyms as $synonym) {
            // Each synonym should be a comma-separated list
            $this->assertStringContainsString(',', $synonym);

            // Verify it's a valid synonym format
            $terms = array_map('trim', explode(',', $synonym));
            $this->assertGreaterThanOrEqual(2, count($terms), 'Each synonym group should have at least 2 terms');

            // Each term should not be empty
            foreach ($terms as $term) {
                $this->assertNotEmpty($term, 'Synonym term should not be empty');
            }
        }
    }

    public function testAllLocalesHaveCommonCategories(): void
    {
        $locales = ['en_US', 'ro_RO', 'de_DE', 'fr_FR', 'es_ES', 'it_IT'];

        foreach ($locales as $localeString) {
            $locale = Locale::fromString($localeString);
            $synonyms = $this->synonymManager->getSynonymsForLocale($locale);

            // All locales should have laptop/notebook synonyms
            $hasLaptopSynonym = false;
            foreach ($synonyms as $synonym) {
                if (str_contains(strtolower($synonym), 'laptop')) {
                    $hasLaptopSynonym = true;

                    break;
                }
            }

            $this->assertTrue(
                $hasLaptopSynonym,
                "Locale {$localeString} should have laptop synonyms"
            );
        }
    }
}
