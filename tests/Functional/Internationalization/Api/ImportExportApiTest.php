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
 * @group functional
 */
final class ImportExportApiTest extends ApiTestCase
{
    private static string $tenantId;

    public static function setUpBeforeClass(): void
    {
        self::$tenantId = TenantId::generate()->toString();
    }

    public function testImportTranslations_ValidCSV_ReturnsSuccess(): void
    {
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
        $client->request('POST', '/api/translations/import', [
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

    public function testImportTranslations_ValidJSON_ReturnsSuccess(): void
    {
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
        $client->request('POST', '/api/translations/import', [
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

    public function testImportTranslations_DryRun_DoesNotPersist(): void
    {
        $client = static::createClient();

        $csv = "locale,domain,key,value\nen,messages,test.dryrun,Test";
        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tempFile, $csv);

        // First dry run
        $client->request('POST', '/api/translations/import', [
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
        $client->request('GET', '/api/translations/export', [
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
            if ($item['key'] === 'test.dryrun') {
                $hasDryRun = true;
                break;
            }
        }
        $this->assertFalse($hasDryRun, 'Dry run should not persist translations');

        @unlink($tempFile);
    }

    public function testImportTranslations_MissingFile_Returns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/translations/import', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
            'extra' => [
                'parameters' => ['format' => 'csv'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testImportTranslations_InvalidFormat_Returns400(): void
    {
        $client = static::createClient();

        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tempFile, 'test');

        $client->request('POST', '/api/translations/import', [
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

    public function testExportTranslations_CSV_ReturnsFile(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/export', [
            'query' => ['format' => 'csv'],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/csv; charset=utf-8');
        $this->assertResponseHasHeader('content-disposition');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('locale,domain,key,value', $content);
    }

    public function testExportTranslations_JSON_ReturnsFile(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/export', [
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

    public function testExportTranslations_WithDomainFilter_FiltersResults(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/export', [
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

    public function testExportTranslations_WithLocaleFilter_FiltersResults(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/export', [
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

    public function testExportTranslations_MissingFormat_Returns400(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/export', [
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testExportTranslations_InvalidFormat_Returns400(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations/export', [
            'query' => ['format' => 'xml'],
            'headers' => ['X-Tenant-ID' => self::$tenantId],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testImportExport_RoundTrip_PreservesData(): void
    {
        $client = static::createClient();

        // Import test data
        $originalData = [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'roundtrip.test', 'value' => 'Test Value'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'roundtrip.test', 'value' => 'Valeur de Test'],
        ];

        $json = json_encode($originalData);
        $tempFile = tempnam(sys_get_temp_dir(), 'roundtrip_');
        file_put_contents($tempFile, $json);

        $client->request('POST', '/api/translations/import', [
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
        $client->request('GET', '/api/translations/export', [
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
            if ($item['key'] === 'roundtrip.test') {
                if ($item['locale'] === 'en') {
                    $this->assertEquals('Test Value', $item['value']);
                    $foundEn = true;
                } elseif ($item['locale'] === 'fr') {
                    $this->assertEquals('Valeur de Test', $item['value']);
                    $foundFr = true;
                }
            }
        }

        $this->assertTrue($foundEn && $foundFr, 'Round trip should preserve both translations');

        @unlink($tempFile);
    }
}
