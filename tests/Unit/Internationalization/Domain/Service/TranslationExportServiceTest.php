<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Service;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Internationalization\Domain\Service\TranslationExportService;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationExportService::class)]
final class TranslationExportServiceTest extends TestCase
{
    private TranslationEntryRepositoryInterface&MockObject $repository;
    private TranslationExportService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationEntryRepositoryInterface::class);
        $this->service = new TranslationExportService($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------
    // export() — filter handling
    // -------

    #[Test]
    public function exportCallsRepositoryWithEmptyFiltersWhenNoneProvided(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: [],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->export($this->tenantId);
    }

    #[Test]
    public function exportPassesDomainFilterToRepository(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['domain' => 'messages'],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->export($this->tenantId, ['domain' => 'messages']);
    }

    #[Test]
    public function exportPassesLocaleFilterToRepository(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['locale' => 'fr'],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->export($this->tenantId, ['locale' => 'fr']);
    }

    #[Test]
    public function exportPassesCreatedAfterFilterToRepository(): void
    {
        $date = '2025-01-01T00:00:00+00:00';

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['createdAfter' => $date],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->export($this->tenantId, ['createdAfter' => $date]);
    }

    #[Test]
    public function exportPassesCreatedBeforeFilterToRepository(): void
    {
        $date = '2025-12-31T00:00:00+00:00';

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['createdBefore' => $date],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->export($this->tenantId, ['createdBefore' => $date]);
    }

    #[Test]
    public function exportPassesUpdatedAfterFilterToRepository(): void
    {
        $date = '2025-06-01T00:00:00+00:00';

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['updatedAfter' => $date],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->export($this->tenantId, ['updatedAfter' => $date]);
    }

    #[Test]
    public function exportIgnoresUnrecognisedFilters(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: [],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        // 'unknownKey' should be stripped out
        $this->service->export($this->tenantId, ['unknownKey' => 'value']);
    }

    #[Test]
    public function exportReturnsEntryArrayFromRepository(): void
    {
        $entry = $this->makeEntry('en', 'messages', 'hello', 'Hello');

        $this->repository
            ->method('findAll')
            ->willReturn([$entry]);

        $result = $this->service->export($this->tenantId);

        self::assertCount(1, $result);
        self::assertSame($entry, $result[0]);
    }

    // -------
    // exportByDomain()
    // -------

    #[Test]
    public function exportByDomainPassesDomainValueToRepository(): void
    {
        $domain = TranslationDomain::fromString('messages');

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['domain' => 'messages'],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->exportByDomain($this->tenantId, $domain);
    }

    // -------
    // exportByLocale()
    // -------

    #[Test]
    public function exportByLocalePassesLocaleValueToRepository(): void
    {
        $locale = LanguageCode::fromString('fr');

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['locale' => 'fr'],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->exportByLocale($this->tenantId, $locale);
    }

    // -------
    // exportByDomainAndLocale()
    // -------

    #[Test]
    public function exportByDomainAndLocalePassesBothFilters(): void
    {
        $domain = TranslationDomain::fromString('validators');
        $locale = LanguageCode::fromString('de');

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: $this->tenantId,
                filters: ['domain' => 'validators', 'locale' => 'de'],
                page: 1,
                perPage: 100000,
            )
            ->willReturn([]);

        $this->service->exportByDomainAndLocale($this->tenantId, $domain, $locale);
    }

    // -------
    // toArrayFormat()
    // -------

    #[Test]
    public function toArrayFormatReturnsCorrectStructure(): void
    {
        $entry = $this->makeEntry('en', 'messages', 'greeting.hello', 'Hello World');

        $result = $this->service->toArrayFormat([$entry]);

        self::assertCount(1, $result);
        self::assertSame('en', $result[0]['locale']);
        self::assertSame('messages', $result[0]['domain']);
        self::assertSame('greeting.hello', $result[0]['key']);
        self::assertSame('Hello World', $result[0]['value']);
    }

    #[Test]
    public function toArrayFormatHandlesMultipleEntries(): void
    {
        $entry1 = $this->makeEntry('en', 'messages', 'hello', 'Hello');
        $entry2 = $this->makeEntry('fr', 'validators', 'email.invalid', 'Email invalide');

        $result = $this->service->toArrayFormat([$entry1, $entry2]);

        self::assertCount(2, $result);
        self::assertSame('en', $result[0]['locale']);
        self::assertSame('fr', $result[1]['locale']);
        self::assertSame('validators', $result[1]['domain']);
    }

    #[Test]
    public function toArrayFormatReturnsEmptyArrayForNoEntries(): void
    {
        self::assertSame([], $this->service->toArrayFormat([]));
    }

    #[Test]
    public function toArrayFormatContainsRequiredKeys(): void
    {
        $entry = $this->makeEntry('en', 'messages', 'key', 'Value');

        $result = $this->service->toArrayFormat([$entry]);

        self::assertArrayHasKey('locale', $result[0]);
        self::assertArrayHasKey('domain', $result[0]);
        self::assertArrayHasKey('key', $result[0]);
        self::assertArrayHasKey('value', $result[0]);
    }

    // -------
    // Helper
    // -------

    private function makeEntry(
        string $locale,
        string $domain,
        string $key,
        string $value,
    ): TranslationEntry {
        return TranslationEntry::create(
            tenantId: $this->tenantId,
            locale: LanguageCode::fromString($locale),
            domain: TranslationDomain::fromString($domain),
            key: TranslationKey::fromString($key),
            value: TranslationValue::fromString($value),
        );
    }
}
