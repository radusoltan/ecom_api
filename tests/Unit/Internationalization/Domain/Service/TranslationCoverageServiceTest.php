<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Service;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Internationalization\Domain\Service\TranslationCoverageService;
use App\Internationalization\Domain\Service\TranslationStatistics;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationCoverageService::class)]
#[CoversClass(TranslationStatistics::class)]
final class TranslationCoverageServiceTest extends TestCase
{
    private TranslationEntryRepositoryInterface&MockObject $repository;
    private TranslationCoverageService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationEntryRepositoryInterface::class);
        $this->service = new TranslationCoverageService($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------
    // findMissingTranslations()
    // -------

    #[Test]
    public function findMissingTranslationsCallsRepositoryWithTargetLocale(): void
    {
        $targetLocale = LanguageCode::fromString('fr');
        $missing = [
            ['key' => 'hello.world', 'domain' => 'messages'],
        ];

        $this->repository
            ->expects(self::once())
            ->method('findMissingInLocale')
            ->with(
                tenantId: $this->tenantId,
                sourceLocale: self::callback(fn (LanguageCode $l) => 'en' === $l->value()),
                targetLocale: self::callback(fn (LanguageCode $l) => 'fr' === $l->value()),
                domain: null,
            )
            ->willReturn($missing);

        $result = $this->service->findMissingTranslations($this->tenantId, $targetLocale);

        self::assertSame($missing, $result);
    }

    #[Test]
    public function findMissingTranslationsUsesDefaultSourceLocaleWhenNotProvided(): void
    {
        $targetLocale = LanguageCode::fromString('de');

        $this->repository
            ->expects(self::once())
            ->method('findMissingInLocale')
            ->with(
                tenantId: $this->tenantId,
                sourceLocale: self::callback(fn (LanguageCode $l) => 'en' === $l->value()),
                targetLocale: self::anything(),
                domain: null,
            )
            ->willReturn([]);

        $this->service->findMissingTranslations($this->tenantId, $targetLocale);
    }

    #[Test]
    public function findMissingTranslationsUsesProvidedSourceLocale(): void
    {
        $targetLocale = LanguageCode::fromString('de');
        $sourceLocale = LanguageCode::fromString('fr');

        $this->repository
            ->expects(self::once())
            ->method('findMissingInLocale')
            ->with(
                tenantId: $this->tenantId,
                sourceLocale: self::callback(fn (LanguageCode $l) => 'fr' === $l->value()),
                targetLocale: self::anything(),
                domain: null,
            )
            ->willReturn([]);

        $this->service->findMissingTranslations($this->tenantId, $targetLocale, $sourceLocale);
    }

    #[Test]
    public function findMissingTranslationsPassesDomainFilterWhenProvided(): void
    {
        $targetLocale = LanguageCode::fromString('fr');
        $domain = TranslationDomain::fromString('messages');

        $this->repository
            ->expects(self::once())
            ->method('findMissingInLocale')
            ->with(
                tenantId: $this->tenantId,
                sourceLocale: self::anything(),
                targetLocale: self::anything(),
                domain: self::callback(fn (TranslationDomain $d) => 'messages' === $d->value()),
            )
            ->willReturn([]);

        $this->service->findMissingTranslations($this->tenantId, $targetLocale, null, $domain);
    }

    #[Test]
    public function findMissingTranslationsReturnsEmptyArrayWhenNothingMissing(): void
    {
        $this->repository
            ->method('findMissingInLocale')
            ->willReturn([]);

        $result = $this->service->findMissingTranslations(
            $this->tenantId,
            LanguageCode::fromString('fr'),
        );

        self::assertSame([], $result);
    }

    // -------
    // getStatistics()
    // -------

    #[Test]
    public function getStatisticsReturnsTranslationStatisticsInstance(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('getStatistics')
            ->with($this->tenantId)
            ->willReturn([
                'byDomain' => [],
                'byLocale' => [],
                'overall' => ['totalKeys' => 0, 'domains' => 0],
            ]);

        $result = $this->service->getStatistics($this->tenantId);

        self::assertInstanceOf(TranslationStatistics::class, $result);
    }

    #[Test]
    public function getStatisticsWrapsRepositoryDataInValueObject(): void
    {
        $rawData = [
            'byDomain' => [
                'messages' => ['totalKeys' => 100, 'locales' => ['en' => ['translatedKeys' => 100, 'coverage' => 100.0]]],
            ],
            'byLocale' => [
                'en' => ['totalKeys' => 100, 'translatedKeys' => 100, 'coverage' => 100.0, 'domains' => []],
            ],
            'overall' => ['totalKeys' => 100, 'domains' => 1],
        ];

        $this->repository
            ->method('getStatistics')
            ->willReturn($rawData);

        $stats = $this->service->getStatistics($this->tenantId);

        self::assertSame($rawData, $stats->toArray());
    }

    // -------
    // getCoverageHeatmap()
    // -------

    #[Test]
    public function getCoverageHeatmapReturnsDomainLocaleMatrix(): void
    {
        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'byDomain' => [
                    'messages' => [
                        'totalKeys' => 100,
                        'locales' => [
                            'en' => ['translatedKeys' => 100, 'coverage' => 100.0],
                            'fr' => ['translatedKeys' => 80, 'coverage' => 80.0],
                        ],
                    ],
                ],
                'byLocale' => [],
                'overall' => ['totalKeys' => 100, 'domains' => 1],
            ]);

        $heatmap = $this->service->getCoverageHeatmap($this->tenantId);

        self::assertArrayHasKey('messages', $heatmap);
        self::assertSame(100.0, $heatmap['messages']['en']);
        self::assertSame(80.0, $heatmap['messages']['fr']);
    }

    #[Test]
    public function getCoverageHeatmapReturnsEmptyArrayWhenNoData(): void
    {
        $this->repository
            ->method('getStatistics')
            ->willReturn(['byDomain' => [], 'byLocale' => [], 'overall' => ['totalKeys' => 0, 'domains' => 0]]);

        $heatmap = $this->service->getCoverageHeatmap($this->tenantId);

        self::assertSame([], $heatmap);
    }

    // -------
    // getAvailableLocales()
    // -------

    #[Test]
    public function getAvailableLocalesReturnsLocaleStrings(): void
    {
        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'byDomain' => [],
                'byLocale' => [
                    'en' => [],
                    'fr' => [],
                    'de' => [],
                ],
                'overall' => ['totalKeys' => 0, 'domains' => 0],
            ]);

        $locales = $this->service->getAvailableLocales($this->tenantId);

        self::assertSame(['en', 'fr', 'de'], $locales);
    }

    #[Test]
    public function getAvailableLocalesReturnsEmptyArrayWhenNoData(): void
    {
        $this->repository
            ->method('getStatistics')
            ->willReturn(['byDomain' => [], 'byLocale' => [], 'overall' => ['totalKeys' => 0, 'domains' => 0]]);

        self::assertSame([], $this->service->getAvailableLocales($this->tenantId));
    }

    // -------
    // getAvailableDomains()
    // -------

    #[Test]
    public function getAvailableDomainsReturnsDomainStrings(): void
    {
        $this->repository
            ->method('getStatistics')
            ->willReturn([
                'byDomain' => [
                    'messages' => ['totalKeys' => 10, 'locales' => []],
                    'validators' => ['totalKeys' => 5, 'locales' => []],
                ],
                'byLocale' => [],
                'overall' => ['totalKeys' => 15, 'domains' => 2],
            ]);

        $domains = $this->service->getAvailableDomains($this->tenantId);

        self::assertSame(['messages', 'validators'], $domains);
    }

    #[Test]
    public function getAvailableDomainsReturnsEmptyArrayWhenNoData(): void
    {
        $this->repository
            ->method('getStatistics')
            ->willReturn(['byDomain' => [], 'byLocale' => [], 'overall' => ['totalKeys' => 0, 'domains' => 0]]);

        self::assertSame([], $this->service->getAvailableDomains($this->tenantId));
    }

    // -------
    // TranslationStatistics value object
    // -------

    #[Test]
    public function statisticsGetByDomainReturnsEmptyWhenMissing(): void
    {
        $stats = new TranslationStatistics([]);

        self::assertSame([], $stats->getByDomain());
    }

    #[Test]
    public function statisticsGetByLocaleReturnsEmptyWhenMissing(): void
    {
        $stats = new TranslationStatistics([]);

        self::assertSame([], $stats->getByLocale());
    }

    #[Test]
    public function statisticsGetOverallReturnsDefaultWhenMissing(): void
    {
        $stats = new TranslationStatistics([]);

        self::assertSame(['totalKeys' => 0, 'domains' => 0], $stats->getOverall());
    }

    #[Test]
    public function statisticsGetCoverageForDomainLocaleReturnsNullWhenMissing(): void
    {
        $stats = new TranslationStatistics([]);

        self::assertNull($stats->getCoverageForDomainLocale('messages', 'en'));
    }

    #[Test]
    public function statisticsGetCoverageForDomainLocaleReturnsValue(): void
    {
        $stats = new TranslationStatistics([
            'byDomain' => ['messages' => ['locales' => ['en' => ['coverage' => 95.5]]]],
        ]);

        self::assertSame(95.5, $stats->getCoverageForDomainLocale('messages', 'en'));
    }

    #[Test]
    public function statisticsGetTranslatedKeysForLocaleReturnsZeroWhenMissing(): void
    {
        $stats = new TranslationStatistics([]);

        self::assertSame(0, $stats->getTranslatedKeysForLocale('en'));
    }

    #[Test]
    public function statisticsGetTranslatedKeysForLocaleReturnsValue(): void
    {
        $stats = new TranslationStatistics([
            'byLocale' => ['en' => ['translatedKeys' => 42]],
        ]);

        self::assertSame(42, $stats->getTranslatedKeysForLocale('en'));
    }

    #[Test]
    public function statisticsGetCoverageForLocaleReturnsZeroWhenMissing(): void
    {
        $stats = new TranslationStatistics([]);

        self::assertSame(0.0, $stats->getCoverageForLocale('en'));
    }

    #[Test]
    public function statisticsGetCoverageForLocaleReturnsValue(): void
    {
        $stats = new TranslationStatistics([
            'byLocale' => ['en' => ['coverage' => 99.5]],
        ]);

        self::assertSame(99.5, $stats->getCoverageForLocale('en'));
    }

    #[Test]
    public function statisticsGetHeatmapDataBuildsMatrix(): void
    {
        $stats = new TranslationStatistics([
            'byDomain' => [
                'messages' => [
                    'totalKeys' => 10,
                    'locales' => [
                        'en' => ['translatedKeys' => 10, 'coverage' => 100.0],
                        'fr' => ['translatedKeys' => 5, 'coverage' => 50.0],
                    ],
                ],
                'validators' => [
                    'totalKeys' => 5,
                    'locales' => [
                        'en' => ['translatedKeys' => 5, 'coverage' => 100.0],
                    ],
                ],
            ],
        ]);

        $heatmap = $stats->getHeatmapData();

        self::assertSame(100.0, $heatmap['messages']['en']);
        self::assertSame(50.0, $heatmap['messages']['fr']);
        self::assertSame(100.0, $heatmap['validators']['en']);
    }

    #[Test]
    public function statisticsGetLocalesReturnsKeys(): void
    {
        $stats = new TranslationStatistics([
            'byLocale' => ['en' => [], 'ro' => []],
        ]);

        self::assertSame(['en', 'ro'], $stats->getLocales());
    }

    #[Test]
    public function statisticsGetDomainsReturnsKeys(): void
    {
        $stats = new TranslationStatistics([
            'byDomain' => ['messages' => [], 'admin' => []],
        ]);

        self::assertSame(['messages', 'admin'], $stats->getDomains());
    }
}
