<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internationalization\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * @covers \App\Internationalization\Infrastructure\ApiPlatform\State\ImportTranslationsProcessor
 * @covers \App\Internationalization\Infrastructure\ApiPlatform\State\ExportTranslationsProvider
 * @covers \App\Internationalization\Application\Command\ImportTranslationsHandler
 * @covers \App\Internationalization\Application\Query\ExportTranslationsHandler
 * @covers \App\Internationalization\Domain\Service\TranslationImportService
 * @covers \App\Internationalization\Domain\Service\TranslationExportService
 *
 * @group functional
 */
final class ImportExportApiTest extends ApiTestCase
{
    private static string $tenantId;

    public static function setUpBeforeClass(): void
    {
        self::$tenantId = TenantId::generate()->toString();
    }

    public function testImportTranslationsValidCSVReturnsSuccess(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/import endpoint.');

        $client = static::createClient();

        // Create CSV content
        $csv = <<<CSV
locale,domain,key,value
en,messages,test.hello,Hello
fr,messages,test.hello,Bonjour
de,messages,test.hello,Guten Tag
CSV;

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tempFile, $csv);

        // Upload file
        $client->request('POST', '/api/v1/translations/import', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
            'extra' => [
                'files' => [
                    'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                        $tempFile,
                        'translations.csv',
                        'text/csv',
                        null,
                        true
                    ),
                ],
                'parameters' => [
                    'format' => 'csv',
                    'dryRun' => 'false',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('processed', $data);
        $this->assertArrayHasKey('created', $data);
        $this->assertArrayHasKey('updated', $data);
        $this->assertArrayHasKey('skipped', $data);
        $this->assertArrayHasKey('successful', $data);

        $this->assertEquals(3, $data['processed']);
        $this->assertEquals(3, $data['created']);
        $this->assertEquals(0, $data['updated']);
        $this->assertTrue($data['successful']);

        // Cleanup
        @unlink($tempFile);
    }

    public function testImportTranslationsValidJSONReturnsSuccess(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/import endpoint.');

        $client = static::createClient();

        // Create JSON content
        $json = json_encode([
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'test.welcome', 'value' => 'Welcome'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'test.welcome', 'value' => 'Bienvenue'],
        ]);

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tempFile, $json);

        // Upload file
        $client->request('POST', '/api/v1/translations/import', [
            'headers' => [
                'X-Tenant-ID' => self::$tenantId,
            ],
            'extra' => [
                'files' => [
                    'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                        $tempFile,
                        'translations.json',
                        'application/json',
                        null,
                        true
                    ),
                ],
                'parameters' => [
                    'format' => 'json',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(2, $data['processed']);
        $this->assertTrue($data['successful']);

        // Cleanup
        @unlink($tempFile);
    }

    public function testImportTranslationsDryRunDoesNotPersist(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/import endpoint.');

        $client = static::createClient();

        $csv = "locale,domain,key,value\nen,messages,test.dryrun,Test";
        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tempFile, $csv);

        // First dry run
        $client->request('POST', '/api/v1/translations/import', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
            'extra' => [
                'files' => [
                    'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                        $tempFile,
                        'translations.csv',
                        'text/csv',
                        null,
                        true
                    ),
                ],
                'parameters' => [
                    'format' => 'csv',
                    'dryRun' => 'true',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $dryRunData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(1, $dryRunData['processed']);

        // Export to verify nothing was saved
        $client->request('GET', '/api/v1/translations/export', [
            'query' => [
                'format' => 'json',
                'domain' => 'messages',
                'locale' => 'en',
            ],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $exportData = json_decode($client->getResponse()->getContent(), true);

        // Check that test.dryrun was not persisted
        $hasDryRun = false;
        foreach ($exportData as $item) {
            if ('test.dryrun' === $item['key']) {
                $hasDryRun = true;

                break;
            }
        }
        $this->assertFalse($hasDryRun, 'Dry run should not persist translations');

        @unlink($tempFile);
    }

    public function testImportTranslationsMissingFileReturns400(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/import endpoint.');

        $client = static::createClient();

        $client->request('POST', '/api/v1/translations/import', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
            'extra' => [
                'parameters' => ['format' => 'csv'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testImportTranslationsInvalidFormatReturns400(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/import endpoint.');

        $client = static::createClient();

        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tempFile, 'test');

        $client->request('POST', '/api/v1/translations/import', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
            'extra' => [
                'files' => [
                    'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                        $tempFile,
                        'test.txt',
                        'text/plain',
                        null,
                        true
                    ),
                ],
                'parameters' => ['format' => 'xml'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        @unlink($tempFile);
    }

    public function testExportTranslationsCSVReturnsFile(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/export endpoint.');

        $client = static::createClient();

        $client->request('GET', '/api/v1/translations/export', [
            'query' => ['format' => 'csv'],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/csv; charset=utf-8');
        $this->assertResponseHasHeader('content-disposition');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('locale,domain,key,value', $content);
    }

    public function testExportTranslationsJSONReturnsFile(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/export endpoint.');

        $client = static::createClient();

        $client->request('GET', '/api/v1/translations/export', [
            'query' => ['format' => 'json'],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
        $this->assertResponseHasHeader('content-disposition');

        $content = $client->getResponse()->getContent();
        $data = json_decode($content, true);
        $this->assertIsArray($data);
    }

    public function testExportTranslationsWithDomainFilterFiltersResults(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/export endpoint.');

        $client = static::createClient();

        $client->request('GET', '/api/v1/translations/export', [
            'query' => [
                'format' => 'json',
                'domain' => 'messages',
            ],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // All results should have domain = messages
        foreach ($data as $item) {
            $this->assertEquals('messages', $item['domain']);
        }
    }

    public function testExportTranslationsWithLocaleFilterFiltersResults(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/export endpoint.');

        $client = static::createClient();

        $client->request('GET', '/api/v1/translations/export', [
            'query' => [
                'format' => 'json',
                'locale' => 'en',
            ],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // All results should have locale = en
        foreach ($data as $item) {
            $this->assertEquals('en', $item['locale']);
        }
    }

    public function testExportTranslationsMissingFormatReturns400(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/export endpoint.');

        $client = static::createClient();

        $client->request('GET', '/api/v1/translations/export', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testExportTranslationsInvalidFormatReturns400(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/export endpoint.');

        $client = static::createClient();

        $client->request('GET', '/api/v1/translations/export', [
            'query' => ['format' => 'xml'],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testImportExportRoundTripPreservesData(): void
    {
        $this->markTestSkipped('Import/Export routes not yet implemented. Waiting for /api/v1/translations/import endpoint.');

        $client = static::createClient();

        // Import test data
        $originalData = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'roundtrip.test', 'value' => 'Test Value'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'roundtrip.test', 'value' => 'Valeur de Test'],
        ];

        $json = json_encode($originalData);
        $tempFile = tempnam(sys_get_temp_dir(), 'roundtrip_');
        file_put_contents($tempFile, $json);

        $client->request('POST', '/api/v1/translations/import', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
            'extra' => [
                'files' => [
                    'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                        $tempFile,
                        'roundtrip.json',
                        'application/json',
                        null,
                        true
                    ),
                ],
                'parameters' => ['format' => 'json'],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Export and verify
        $client->request('GET', '/api/v1/translations/export', [
            'query' => [
                'format' => 'json',
                'domain' => 'messages',
            ],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseIsSuccessful();

        $exported = json_decode($client->getResponse()->getContent(), true);

        // Find our test translations
        $foundEn = false;
        $foundFr = false;

        foreach ($exported as $item) {
            if ('roundtrip.test' === $item['key']) {
                if ('en' === $item['locale']) {
                    $this->assertEquals('Test Value', $item['value']);
                    $foundEn = true;
                } elseif ('fr' === $item['locale']) {
                    $this->assertEquals('Valeur de Test', $item['value']);
                    $foundFr = true;
                }
            }
        }

        $this->assertTrue($foundEn && $foundFr, 'Round trip should preserve both translations');

        @unlink($tempFile);
    }
}
