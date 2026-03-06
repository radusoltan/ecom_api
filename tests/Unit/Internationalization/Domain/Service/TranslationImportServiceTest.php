<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Service;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Internationalization\Domain\Service\ImportResult;
use App\Internationalization\Domain\Service\TranslationImportService;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationImportService::class)]
#[CoversClass(ImportResult::class)]
final class TranslationImportServiceTest extends TestCase
{
    private TranslationEntryRepositoryInterface&MockObject $repository;
    private TranslationImportService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationEntryRepositoryInterface::class);
        $this->service = new TranslationImportService($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------
    // import() — valid data, new entries
    // -------

    #[Test]
    public function importCreatesNewEntryWhenKeyDoesNotExist(): void
    {
        $this->repository->method('findByKey')->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(TranslationEntry::class));

        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        self::assertSame(1, $result->getProcessed());
        self::assertSame(1, $result->getCreated());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importUpdatesExistingEntryWhenKeyAlreadyExists(): void
    {
        $existingEntry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: LanguageCode::fromString('en'),
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('hello'),
            value: TranslationValue::fromString('Old value'),
        );

        $this->repository->method('findByKey')->willReturn($existingEntry);
        $this->repository
            ->expects(self::once())
            ->method('save');

        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'New value'],
        ]);

        self::assertSame(1, $result->getProcessed());
        self::assertSame(0, $result->getCreated());
        self::assertSame(1, $result->getUpdated());
    }

    #[Test]
    public function importProcessesMultipleEntries(): void
    {
        $this->repository->method('findByKey')->willReturn(null);
        $this->repository->expects(self::exactly(3))->method('save');

        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'key1', 'value' => 'Value 1'],
            ['locale' => 'fr', 'domain' => 'messages', 'key' => 'key2', 'value' => 'Value 2'],
            ['locale' => 'de', 'domain' => 'validators', 'key' => 'key3', 'value' => 'Value 3'],
        ]);

        self::assertSame(3, $result->getProcessed());
        self::assertSame(3, $result->getCreated());
    }

    // -------
    // import() — validation errors
    // -------

    #[Test]
    public function importAddsErrorWhenRequiredFieldsMissing(): void
    {
        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages'], // missing key, value
        ]);

        self::assertTrue($result->hasErrors());
        self::assertCount(1, $result->getErrors());
        self::assertStringContainsString('Missing required fields', $result->getErrors()[0]['message']);
        self::assertSame(1, $result->getErrors()[0]['line']);
    }

    #[Test]
    public function importAddsErrorWhenLocaleKeyMissing(): void
    {
        $result = $this->service->import($this->tenantId, [
            ['domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        self::assertTrue($result->hasErrors());
    }

    #[Test]
    public function importAddsWarningForInvalidLocale(): void
    {
        $result = $this->service->import($this->tenantId, [
            ['locale' => 'zh', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        self::assertTrue($result->hasWarnings());
        self::assertCount(1, $result->getWarnings());
        self::assertStringContainsString('Invalid locale', $result->getWarnings()[0]['message']);
    }

    #[Test]
    public function importAddsWarningForInvalidDomain(): void
    {
        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'unknown_domain', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        self::assertTrue($result->hasWarnings());
        self::assertStringContainsString('Invalid domain', $result->getWarnings()[0]['message']);
    }

    #[Test]
    public function importAddsWarningForEmptyValue(): void
    {
        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => ''],
        ]);

        self::assertTrue($result->hasWarnings());
        self::assertStringContainsString('Empty translation value', $result->getWarnings()[0]['message']);
    }

    #[Test]
    public function importAddsWarningForWhitespaceOnlyValue(): void
    {
        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => '   '],
        ]);

        self::assertTrue($result->hasWarnings());
    }

    #[Test]
    public function importReportsCorrectLineNumbersInErrors(): void
    {
        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'valid', 'value' => 'Valid'],
            ['locale' => 'zh', 'domain' => 'messages', 'key' => 'invalid', 'value' => 'Value'], // line 2
            ['locale' => 'en', 'domain' => 'unknown', 'key' => 'also_invalid', 'value' => 'Value'], // line 3
        ]);

        $warnings = $result->getWarnings();
        self::assertSame(2, $warnings[0]['line']);
        self::assertSame(3, $warnings[1]['line']);
    }

    // -------
    // import() — dry-run mode
    // -------

    #[Test]
    public function importInDryRunDoesNotPersistData(): void
    {
        $this->repository->method('findByKey')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ], dryRun: true);

        self::assertSame(1, $result->getCreated());
        self::assertSame(1, $result->getProcessed());
    }

    #[Test]
    public function importInDryRunCountsUpdatesWithoutPersisting(): void
    {
        $existingEntry = TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: LanguageCode::fromString('en'),
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('hello'),
            value: TranslationValue::fromString('Old value'),
        );

        $this->repository->method('findByKey')->willReturn($existingEntry);
        $this->repository->expects(self::never())->method('save');

        $result = $this->service->import($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'New value'],
        ], dryRun: true);

        self::assertSame(1, $result->getUpdated());
    }

    // -------
    // validateImport()
    // -------

    #[Test]
    public function validateImportRunsAsADryRun(): void
    {
        $this->repository->method('findByKey')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $result = $this->service->validateImport($this->tenantId, [
            ['locale' => 'en', 'domain' => 'messages', 'key' => 'hello', 'value' => 'Hello'],
        ]);

        self::assertSame(1, $result->getCreated());
    }

    // -------
    // getValidDomains() / getValidLocales()
    // -------

    #[Test]
    public function getValidDomainsReturnsNonEmptyList(): void
    {
        $domains = TranslationImportService::getValidDomains();

        self::assertNotEmpty($domains);
        self::assertContains('messages', $domains);
        self::assertContains('validators', $domains);
    }

    #[Test]
    public function getValidLocalesReturnsNonEmptyList(): void
    {
        $locales = TranslationImportService::getValidLocales();

        self::assertNotEmpty($locales);
        self::assertContains('en', $locales);
        self::assertContains('fr', $locales);
    }

    // -------
    // ImportResult — standalone tests
    // -------

    #[Test]
    public function importResultInitialisesWithZeroCounters(): void
    {
        $result = new ImportResult();

        self::assertSame(0, $result->getProcessed());
        self::assertSame(0, $result->getCreated());
        self::assertSame(0, $result->getUpdated());
        self::assertSame(0, $result->getSkipped());
    }

    #[Test]
    public function importResultIncrementProcessedCountsCorrectly(): void
    {
        $result = new ImportResult();
        $result->incrementProcessed();
        $result->incrementProcessed();

        self::assertSame(2, $result->getProcessed());
    }

    #[Test]
    public function importResultIncrementCreatedCountsCorrectly(): void
    {
        $result = new ImportResult();
        $result->incrementCreated();

        self::assertSame(1, $result->getCreated());
    }

    #[Test]
    public function importResultIncrementUpdatedCountsCorrectly(): void
    {
        $result = new ImportResult();
        $result->incrementUpdated();

        self::assertSame(1, $result->getUpdated());
    }

    #[Test]
    public function importResultGetSkippedCountsErrorsPlusWarnings(): void
    {
        $result = new ImportResult();
        $result->addError(1, 'Some error');
        $result->addWarning(2, 'Some warning');
        $result->addWarning(3, 'Another warning');

        self::assertSame(3, $result->getSkipped());
    }

    #[Test]
    public function importResultHasErrorsReturnsTrueWhenErrorsExist(): void
    {
        $result = new ImportResult();
        $result->addError(1, 'Error message');

        self::assertTrue($result->hasErrors());
    }

    #[Test]
    public function importResultHasErrorsReturnsFalseWhenNoErrors(): void
    {
        $result = new ImportResult();

        self::assertFalse($result->hasErrors());
    }

    #[Test]
    public function importResultHasWarningsReturnsTrueWhenWarningsExist(): void
    {
        $result = new ImportResult();
        $result->addWarning(1, 'Warning message');

        self::assertTrue($result->hasWarnings());
    }

    #[Test]
    public function importResultIsSuccessfulReturnsTrueWhenNoErrors(): void
    {
        $result = new ImportResult();
        $result->addWarning(1, 'A warning does not break success');

        self::assertTrue($result->isSuccessful());
    }

    #[Test]
    public function importResultIsSuccessfulReturnsFalseWhenErrorsExist(): void
    {
        $result = new ImportResult();
        $result->addError(1, 'An error');

        self::assertFalse($result->isSuccessful());
    }

    #[Test]
    public function importResultToArrayContainsAllKeys(): void
    {
        $result = new ImportResult();
        $result->incrementProcessed();
        $result->incrementCreated();
        $result->addWarning(1, 'warning');

        $array = $result->toArray();

        self::assertArrayHasKey('processed', $array);
        self::assertArrayHasKey('created', $array);
        self::assertArrayHasKey('updated', $array);
        self::assertArrayHasKey('skipped', $array);
        self::assertArrayHasKey('errors', $array);
        self::assertArrayHasKey('warnings', $array);
        self::assertArrayHasKey('successful', $array);
    }

    #[Test]
    public function importResultToArrayReflectsCorrectValues(): void
    {
        $result = new ImportResult();
        $result->incrementProcessed();
        $result->incrementProcessed();
        $result->incrementCreated();
        $result->incrementUpdated();
        $result->addError(1, 'Error');

        $array = $result->toArray();

        self::assertSame(2, $array['processed']);
        self::assertSame(1, $array['created']);
        self::assertSame(1, $array['updated']);
        self::assertFalse($array['successful']);
    }

    #[Test]
    public function importResultStoresErrorDetails(): void
    {
        $result = new ImportResult();
        $result->addError(5, 'Specific error message');

        $errors = $result->getErrors();

        self::assertCount(1, $errors);
        self::assertSame(5, $errors[0]['line']);
        self::assertSame('Specific error message', $errors[0]['message']);
    }

    #[Test]
    public function importResultStoresWarningDetails(): void
    {
        $result = new ImportResult();
        $result->addWarning(3, 'Specific warning message');

        $warnings = $result->getWarnings();

        self::assertCount(1, $warnings);
        self::assertSame(3, $warnings[0]['line']);
        self::assertSame('Specific warning message', $warnings[0]['message']);
    }
}
