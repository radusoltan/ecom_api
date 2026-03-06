<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Pricing\Application\DTO\ImportRow;
use App\Pricing\Application\Service\ImportValidationService;
use App\Pricing\Application\Service\PricingImportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(PricingImportService::class)]
final class PricingImportServiceTest extends TestCase
{
    private PricingImportService $service;
    private ImportValidationService $validationService;

    protected function setUp(): void
    {
        $this->validationService = new ImportValidationService();
        $this->service = new PricingImportService($this->validationService);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Create a real CSV temp file and wrap it as an UploadedFile in test mode.
     */
    private function makeCsvFile(string $csvContent): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pricing_test_').'.csv';
        file_put_contents($path, $csvContent);

        return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
    }

    /**
     * Create a real JSON temp file and wrap it as an UploadedFile in test mode.
     */
    private function makeJsonFile(string $jsonContent): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pricing_test_').'.json';
        file_put_contents($path, $jsonContent);

        return new UploadedFile($path, 'import.json', 'application/json', null, true);
    }

    /**
     * Create an UploadedFile mock for validation-only tests.
     */
    private function mockFile(
        bool $isValid = true,
        int $size = 1024,
        string $extension = 'csv',
    ): UploadedFile&MockObject {
        $mock = $this->createMock(UploadedFile::class);
        $mock->method('isValid')->willReturn($isValid);
        $mock->method('getSize')->willReturn($size);
        $mock->method('getClientOriginalExtension')->willReturn($extension);

        return $mock;
    }

    // -----------------------------------------------------------------------
    // shouldProcessAsync
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFalseForSmallRowCount(): void
    {
        self::assertFalse($this->service->shouldProcessAsync(0));
    }

    #[Test]
    public function itReturnsFalseAtThreshold(): void
    {
        // Threshold is 1000 — exactly 1000 rows should NOT require async
        self::assertFalse($this->service->shouldProcessAsync(1000));
    }

    #[Test]
    public function itReturnsTrueForLargeRowCount(): void
    {
        self::assertTrue($this->service->shouldProcessAsync(1001));
    }

    #[Test]
    public function itReturnsTrueWayAboveThreshold(): void
    {
        self::assertTrue($this->service->shouldProcessAsync(10000));
    }

    // -----------------------------------------------------------------------
    // parseFile — file validation (via mock)
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenFileIsInvalid(): void
    {
        $file = $this->mockFile(isValid: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file upload');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsWhenFileSizeExceedsLimit(): void
    {
        $tenMbPlusOne = 10 * 1024 * 1024 + 1;
        $file = $this->mockFile(isValid: true, size: $tenMbPlusOne, extension: 'csv');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds maximum');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsForUnsupportedFileExtension(): void
    {
        $file = $this->mockFile(isValid: true, size: 1024, extension: 'xlsx');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only CSV and JSON files are supported');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsForEmptyFileExtension(): void
    {
        $file = $this->mockFile(isValid: true, size: 1024, extension: '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only CSV and JSON files are supported');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsForUnsupportedXmlExtension(): void
    {
        $file = $this->mockFile(isValid: true, size: 100, extension: 'xml');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only CSV and JSON files are supported');

        $this->service->parseFile($file);
    }

    // -----------------------------------------------------------------------
    // parseFile — CSV parsing (real files)
    // -----------------------------------------------------------------------

    #[Test]
    public function itParsesCsvFileAndReturnsImportRows(): void
    {
        $csv = "name,priority\nSummer Sale,100\nWinter Sale,50\n";
        $file = $this->makeCsvFile($csv);

        $rows = $this->service->parseFile($file);

        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(ImportRow::class, $rows);
    }

    #[Test]
    public function itAssignsCorrectRowNumbersForCsv(): void
    {
        $csv = "name,priority\nRow One,10\nRow Two,20\n";
        $file = $this->makeCsvFile($csv);

        $rows = $this->service->parseFile($file);

        // Row numbers start at 2 (header is row 0, first data row gets ++rowNumber starting from 1)
        self::assertSame(2, $rows[0]->rowNumber);
        self::assertSame(3, $rows[1]->rowNumber);
    }

    #[Test]
    public function itMapsCsvHeadersToRowData(): void
    {
        $csv = "name,priority,scope\nSummer Sale,100,product\n";
        $file = $this->makeCsvFile($csv);

        $rows = $this->service->parseFile($file);

        self::assertCount(1, $rows);
        self::assertSame('Summer Sale', $rows[0]->data['name']);
        self::assertSame('100', $rows[0]->data['priority']);
        self::assertSame('product', $rows[0]->data['scope']);
    }

    #[Test]
    public function itSkipsEmptyCsvRows(): void
    {
        // Empty row between two data rows
        $csv = "name,priority\nSummer Sale,100\n,\nWinter Sale,50\n";
        $file = $this->makeCsvFile($csv);

        $rows = $this->service->parseFile($file);

        // The empty row (only commas / empty values) should be skipped
        self::assertCount(2, $rows);
    }

    #[Test]
    public function itThrowsForEmptyCsvFile(): void
    {
        $file = $this->makeCsvFile('');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV file is empty or invalid');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsWhenCsvColumnCountMismatches(): void
    {
        // Header has 2 columns, data row has 3
        $csv = "name,priority\nSummer Sale,100,extra\n";
        $file = $this->makeCsvFile($csv);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV column count mismatch');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itReturnsEmptyArrayForCsvWithHeaderOnly(): void
    {
        $csv = "name,priority\n";
        $file = $this->makeCsvFile($csv);

        $rows = $this->service->parseFile($file);

        self::assertSame([], $rows);
    }

    #[Test]
    public function itHandlesCsvWithExtensionCaseInsensitive(): void
    {
        // Extension matching is case-insensitive (CSV vs csv)
        $path = tempnam(sys_get_temp_dir(), 'pricing_test_').'.CSV';
        file_put_contents($path, "name,priority\nTest,10\n");

        // Simulate file uploaded as CSV (uppercase) — the service calls strtolower()
        $file = new UploadedFile($path, 'import.CSV', 'text/csv', null, true);

        $rows = $this->service->parseFile($file);

        self::assertCount(1, $rows);
    }

    // -----------------------------------------------------------------------
    // parseFile — JSON parsing (real files)
    // -----------------------------------------------------------------------

    #[Test]
    public function itParsesJsonFileAndReturnsImportRows(): void
    {
        $json = json_encode([
            ['name' => 'Summer Sale', 'priority' => 100],
            ['name' => 'Winter Sale', 'priority' => 50],
        ]);
        $file = $this->makeJsonFile($json);

        $rows = $this->service->parseFile($file);

        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(ImportRow::class, $rows);
    }

    #[Test]
    public function itAssignsCorrectRowNumbersForJson(): void
    {
        $json = json_encode([
            ['name' => 'First', 'priority' => 1],
            ['name' => 'Second', 'priority' => 2],
        ]);
        $file = $this->makeJsonFile($json);

        $rows = $this->service->parseFile($file);

        self::assertSame(1, $rows[0]->rowNumber);
        self::assertSame(2, $rows[1]->rowNumber);
    }

    #[Test]
    public function itMapsJsonObjectFieldsToRowData(): void
    {
        $json = json_encode([
            ['name' => 'Flash Sale', 'priority' => 200, 'scope' => 'all'],
        ]);
        $file = $this->makeJsonFile($json);

        $rows = $this->service->parseFile($file);

        self::assertCount(1, $rows);
        self::assertSame('Flash Sale', $rows[0]->data['name']);
        self::assertSame(200, $rows[0]->data['priority']);
        self::assertSame('all', $rows[0]->data['scope']);
    }

    #[Test]
    public function itThrowsForJsonThatIsNotAnArray(): void
    {
        // A JSON string scalar (not an array) — json_decode returns a PHP string, not array
        $json = '"just a plain string"';
        $file = $this->makeJsonFile($json);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON file must contain an array of objects');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsForJsonWithNonObjectElement(): void
    {
        $json = json_encode([
            ['name' => 'Valid'],
            'just a string', // invalid element
        ]);
        $file = $this->makeJsonFile($json);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON format at row');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itReturnsEmptyArrayForEmptyJsonArray(): void
    {
        $file = $this->makeJsonFile('[]');

        $rows = $this->service->parseFile($file);

        self::assertSame([], $rows);
    }

    #[Test]
    public function itThrowsForInvalidJsonSyntax(): void
    {
        $file = $this->makeJsonFile('{invalid json}');

        $this->expectException(\JsonException::class);

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsForJsonExceedingMaxRows(): void
    {
        $data = array_fill(0, 10001, ['name' => 'Row', 'priority' => 1]);
        $json = json_encode($data);
        $file = $this->makeJsonFile($json);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Import file exceeds maximum of 10000 rows');

        $this->service->parseFile($file);
    }

    #[Test]
    public function itThrowsForUnsupportedExtensionInParseFile(): void
    {
        // Use a mock to simulate a valid file with txt extension
        $file = $this->mockFile(isValid: true, size: 100, extension: 'txt');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->parseFile($file);
    }

    // -----------------------------------------------------------------------
    // validatePriceListRows
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNoErrorsForValidPriceListRows(): void
    {
        $rows = [
            new ImportRow(1, ['name' => 'Summer Sale', 'priority' => '100']),
            new ImportRow(2, ['name' => 'Winter Sale', 'priority' => '50']),
        ];

        $errors = $this->service->validatePriceListRows($rows);

        self::assertSame([], $errors);
    }

    #[Test]
    public function itReturnsErrorsIndexedByRowNumberForInvalidPriceListRows(): void
    {
        $rows = [
            new ImportRow(2, ['priority' => '100']), // missing name
            new ImportRow(3, ['name' => 'OK', 'priority' => '50']),
            new ImportRow(5, ['name' => 'Bad', 'priority' => '9999']), // priority out of range
        ];

        $errors = $this->service->validatePriceListRows($rows);

        self::assertArrayHasKey(2, $errors);
        self::assertArrayNotHasKey(3, $errors);
        self::assertArrayHasKey(5, $errors);
    }

    #[Test]
    public function itReturnsEmptyErrorMapForEmptyPriceListRows(): void
    {
        $errors = $this->service->validatePriceListRows([]);

        self::assertSame([], $errors);
    }

    #[Test]
    public function itCollectsMultipleErrorsPerPriceListRow(): void
    {
        $rows = [
            new ImportRow(1, ['scope' => 'invalid']), // missing name, missing priority, bad scope
        ];

        $errors = $this->service->validatePriceListRows($rows);

        self::assertArrayHasKey(1, $errors);
        self::assertGreaterThanOrEqual(3, count($errors[1]));
    }

    // -----------------------------------------------------------------------
    // validatePromotionRows
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNoErrorsForValidPromotionRows(): void
    {
        $rows = [
            new ImportRow(1, [
                'name' => 'Summer Promo',
                'type' => 'automatic',
                'discount_type' => 'percentage',
                'discount_value' => '10',
            ]),
        ];

        $errors = $this->service->validatePromotionRows($rows);

        self::assertSame([], $errors);
    }

    #[Test]
    public function itReturnsErrorsIndexedByRowNumberForInvalidPromotionRows(): void
    {
        $rows = [
            new ImportRow(1, [
                'name' => 'OK Promo',
                'type' => 'automatic',
                'discount_type' => 'percentage',
                'discount_value' => '10',
            ]),
            new ImportRow(2, [
                // missing name and type
                'discount_type' => 'percentage',
                'discount_value' => '10',
            ]),
        ];

        $errors = $this->service->validatePromotionRows($rows);

        self::assertArrayNotHasKey(1, $errors);
        self::assertArrayHasKey(2, $errors);
    }

    #[Test]
    public function itReturnsEmptyErrorMapForEmptyPromotionRows(): void
    {
        $errors = $this->service->validatePromotionRows([]);

        self::assertSame([], $errors);
    }

    #[Test]
    public function itOnlyIncludesRowsWithErrorsInPromotionValidationResult(): void
    {
        $rows = [
            new ImportRow(10, [
                'name' => 'Good',
                'type' => 'automatic',
                'discount_type' => 'percentage',
                'discount_value' => '5',
            ]),
            new ImportRow(11, [
                'name' => 'Bad',
                'type' => 'coupon', // coupon without coupon_code
                'discount_type' => 'percentage',
                'discount_value' => '5',
            ]),
            new ImportRow(12, [
                'name' => 'Also Good',
                'type' => 'flash_sale',
                'discount_type' => 'fixed',
                'discount_value' => '10',
            ]),
        ];

        $errors = $this->service->validatePromotionRows($rows);

        self::assertCount(1, $errors);
        self::assertArrayHasKey(11, $errors);
        self::assertContains('Coupon code is required for coupon type promotions', $errors[11]);
    }
}
