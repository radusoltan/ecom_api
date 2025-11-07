<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internationalization\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * @covers \App\Internationalization\Infrastructure\ApiPlatform\State\MissingTranslationsProvider
 * @covers \App\Internationalization\Infrastructure\ApiPlatform\State\TranslationStatsProvider
 * @covers \App\Internationalization\Application\Query\GetMissingTranslationsHandler
 * @covers \App\Internationalization\Application\Query\GetTranslationStatsHandler
 * @covers \App\Internationalization\Domain\Service\TranslationCoverageService
 *
 * @group functional
 */
final class TranslationCoverageApiTest extends ApiTestCase
{
    private static string $tenantId;

    public static function setUpBeforeClass(): void
    {
        self::$tenantId = TenantId::generate()->toString();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Seed some test translations for coverage testing
        $this->seedTestTranslations();
    }

    public function testGetMissingTranslationsValidRequestReturnsArray(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/missing', [
            'query' => [
                'targetLocale' => 'fr',
                'sourceLocale' => 'en',
            ],
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        // Each item should have 'key' and 'domain' fields
        foreach ($data as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('domain', $item);
            $this->assertIsString($item['key']);
            $this->assertIsString($item['domain']);
        }
    }

    public function testGetMissingTranslationsWithDomainFilterFiltersResults(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/missing', [
            'query' => [
                'targetLocale' => 'fr',
                'sourceLocale' => 'en',
                'domain' => 'messages',
            ],
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // All results should be from messages domain
        foreach ($data as $item) {
            $this->assertEquals('messages', $item['domain']);
        }
    }

    public function testGetMissingTranslationsMissingTargetLocaleReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/missing', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetTranslationStatsValidRequestReturnsStatistics(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/stats', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Verify response structure
        $this->assertArrayHasKey('byDomain', $data);
        $this->assertArrayHasKey('byLocale', $data);
        $this->assertArrayHasKey('overall', $data);

        // Verify overall stats
        $this->assertArrayHasKey('totalKeys', $data['overall']);
        $this->assertArrayHasKey('domains', $data['overall']);
        $this->assertIsInt($data['overall']['totalKeys']);
        $this->assertIsInt($data['overall']['domains']);
    }

    public function testGetTranslationStatsVerifyDomainStructure(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/stats', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Verify byDomain structure
        foreach ($data['byDomain'] as $domain => $domainData) {
            $this->assertIsString($domain);
            $this->assertArrayHasKey('totalKeys', $domainData);
            $this->assertArrayHasKey('locales', $domainData);
            $this->assertIsInt($domainData['totalKeys']);
            $this->assertIsArray($domainData['locales']);

            // Verify locale data within domain
            foreach ($domainData['locales'] as $locale => $localeData) {
                $this->assertIsString($locale);
                $this->assertArrayHasKey('translatedKeys', $localeData);
                $this->assertArrayHasKey('coverage', $localeData);
                $this->assertIsInt($localeData['translatedKeys']);
                $this->assertIsFloat($localeData['coverage']);
                $this->assertGreaterThanOrEqual(0, $localeData['coverage']);
                $this->assertLessThanOrEqual(100, $localeData['coverage']);
            }
        }
    }

    public function testGetTranslationStatsVerifyLocaleStructure(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/stats', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Verify byLocale structure
        foreach ($data['byLocale'] as $locale => $localeData) {
            $this->assertIsString($locale);
            $this->assertArrayHasKey('totalKeys', $localeData);
            $this->assertArrayHasKey('translatedKeys', $localeData);
            $this->assertArrayHasKey('coverage', $localeData);
            $this->assertArrayHasKey('domains', $localeData);

            $this->assertIsInt($localeData['totalKeys']);
            $this->assertIsInt($localeData['translatedKeys']);
            $this->assertIsFloat($localeData['coverage']);
            $this->assertIsArray($localeData['domains']);
        }
    }

    public function testGetTranslationStatsPerformanceUnder500ms(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);

        $client->request('GET', '/api/translations/stats', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(500, $duration, sprintf(
            'Stats endpoint took %.2fms, should be under 500ms',
            $duration
        ));
    }

    public function testGetMissingTranslationsPerformanceUnder500ms(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);

        $client->request('GET', '/api/translations/missing', [
            'query' => [
                'targetLocale' => 'fr',
            ],
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(500, $duration, sprintf(
            'Missing translations endpoint took %.2fms, should be under 500ms',
            $duration
        ));
    }

    public function testCoverageHeatmapCalculatesCorrectly(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/stats', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Verify coverage calculation: coverage = (translatedKeys / totalKeys) * 100
        foreach ($data['byDomain'] as $domain => $domainData) {
            $totalKeys = $domainData['totalKeys'];

            foreach ($domainData['locales'] as $locale => $localeData) {
                $translatedKeys = $localeData['translatedKeys'];
                $coverage = $localeData['coverage'];

                if ($totalKeys > 0) {
                    $expectedCoverage = round(($translatedKeys / $totalKeys) * 100, 2);
                    $this->assertEquals($expectedCoverage, $coverage, sprintf(
                        'Coverage mismatch for %s/%s: expected %.2f, got %.2f',
                        $domain,
                        $locale,
                        $expectedCoverage,
                        $coverage
                    ));
                }
            }
        }
    }

    private function seedTestTranslations(): void
    {
        $client = static::createClient();

        // Seed English translations (complete set)
        $enTranslations = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'test.hello', 'value' => 'Hello'],
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'test.goodbye', 'value' => 'Goodbye'],
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'test.thanks', 'value' => 'Thanks'],
            ['locale' => 'en', 'domain' => 'validators', 'key' => 'test.email', 'value' => 'Invalid email'],
        ];

        // Seed French translations (partial - missing some)
        $frTranslations = [
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'test.hello', 'value' => 'Bonjour'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'test.goodbye', 'value' => 'Au revoir'],
            // Missing: test.thanks, test.email
        ];

        $allTranslations = array_merge($enTranslations, $frTranslations);

        $json = json_encode($allTranslations);
        $tempFile = tempnam(sys_get_temp_dir(), 'coverage_test_');
        file_put_contents($tempFile, $json);

        $client->request('POST', '/api/translations/import', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
            'extra' => [
                'files' => [
                    'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                        $tempFile,
                        'test.json',
                        'application/json',
                        null,
                        true
                    ),
                ],
                'parameters' => ['format' => 'json'],
            ],
        ]);

        @unlink($tempFile);
    }
}
