<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Infrastructure\Persistence\Doctrine\Repository;

use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Infrastructure\Persistence\Doctrine\Repository\DoctrineTranslationEntryRepository;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineTranslationEntryRepository::class)]
final class DoctrineTranslationEntryRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private DoctrineTranslationEntryRepository $repository;
    private TenantId $tenantId;
    private LanguageCode $locale;
    private TranslationDomain $domain;
    private TranslationKey $key;
    private TranslationValue $value;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new DoctrineTranslationEntryRepository($this->connection);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->locale = LanguageCode::fromString('en');
        $this->domain = TranslationDomain::fromString('messages');
        $this->key = TranslationKey::fromString('common.save');
        $this->value = TranslationValue::fromString('Save');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createEntryWithId(int $id): TranslationEntry
    {
        return new TranslationEntry(
            id: $id,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );
    }

    private function createNewEntry(): TranslationEntry
    {
        return new TranslationEntry(
            id: null,
            tenantId: $this->tenantId,
            locale: $this->locale,
            domain: $this->domain,
            key: $this->key,
            value: $this->value,
        );
    }

    private function buildRowData(int $id = 1): array
    {
        return [
            'id' => $id,
            'locale' => 'en',
            'object_class' => 'App\\Translation\\CustomTranslation',
            'field' => 'messages',
            'foreign_key' => '00000000-0000-4000-8000-000000000001:common.save',
            'content' => 'Save',
        ];
    }

    private function createDbalQueryBuilderMock(mixed $fetchAllResult = [], mixed $fetchOneResult = null): DbalQueryBuilder&MockObject
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($fetchAllResult);
        $result->method('fetchOne')->willReturn($fetchOneResult ?? 0);

        $qb = $this->createMock(DbalQueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('executeQuery')->willReturn($result);

        return $qb;
    }

    // -----------------------------------------------------------------------
    // save() — insert (id is null)
    // -----------------------------------------------------------------------

    #[Test]
    public function saveCallsInsertWhenEntryHasNoId(): void
    {
        $entry = $this->createNewEntry();

        $this->connection
            ->expects(self::once())
            ->method('insert')
            ->with(
                'ext_translations',
                self::callback(function (array $data): bool {
                    return 'en' === $data['locale']
                        && 'messages' === $data['field']
                        && 'Save' === $data['content']
                        && str_contains($data['foreign_key'], 'common.save')
                        && 'App\\Translation\\CustomTranslation' === $data['object_class'];
                })
            );

        $this->repository->save($entry);
    }

    #[Test]
    public function saveCallsUpdateWhenEntryHasId(): void
    {
        $entry = $this->createEntryWithId(42);

        $this->connection
            ->expects(self::never())
            ->method('insert');

        $this->connection
            ->expects(self::once())
            ->method('update')
            ->with(
                'ext_translations',
                ['content' => 'Save'],
                ['id' => 42]
            );

        $this->repository->save($entry);
    }

    // -----------------------------------------------------------------------
    // delete()
    // -----------------------------------------------------------------------

    #[Test]
    public function deleteCallsConnectionDelete(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('delete')
            ->with('ext_translations', ['id' => 99]);

        $this->repository->delete(99, $this->tenantId);
    }

    // -----------------------------------------------------------------------
    // findById()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByIdReturnsNullWhenNoRowFound(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM ext_translations WHERE id = ?', [1])
            ->willReturn(false);

        $result = $this->repository->findById(1, $this->tenantId);

        self::assertNull($result);
    }

    #[Test]
    public function findByIdReturnsHydratedEntryWhenRowFound(): void
    {
        $row = $this->buildRowData(1);

        $this->connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM ext_translations WHERE id = ?', [1])
            ->willReturn($row);

        $result = $this->repository->findById(1, $this->tenantId);

        self::assertInstanceOf(TranslationEntry::class, $result);
        self::assertSame(1, $result->id);
        self::assertSame($this->tenantId->toString(), $result->tenantId->toString());
        self::assertSame('en', $result->locale->value());
        self::assertSame('messages', $result->domain->value());
        self::assertSame('common.save', $result->key->value());
        self::assertSame('Save', $result->value->value());
    }

    // -----------------------------------------------------------------------
    // findByKey()
    // -----------------------------------------------------------------------

    #[Test]
    public function findByKeyReturnsNullWhenNoRowFound(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->repository->findByKey(
            $this->tenantId,
            $this->locale,
            $this->domain,
            $this->key,
        );

        self::assertNull($result);
    }

    #[Test]
    public function findByKeyReturnsHydratedEntryWhenFound(): void
    {
        $row = $this->buildRowData(5);
        $expectedForeignKey = $this->tenantId->toString().':'.$this->key->value();

        $this->connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                'SELECT * FROM ext_translations WHERE locale = ? AND field = ? AND foreign_key = ?',
                ['en', 'messages', $expectedForeignKey]
            )
            ->willReturn($row);

        $result = $this->repository->findByKey(
            $this->tenantId,
            $this->locale,
            $this->domain,
            $this->key,
        );

        self::assertInstanceOf(TranslationEntry::class, $result);
        self::assertSame(5, $result->id);
        self::assertSame('en', $result->locale->value());
        self::assertSame('messages', $result->domain->value());
        self::assertSame('common.save', $result->key->value());
        self::assertSame('Save', $result->value->value());
    }

    // -----------------------------------------------------------------------
    // findAll()
    // -----------------------------------------------------------------------

    #[Test]
    public function findAllReturnsEmptyArrayWhenNoResults(): void
    {
        $qb = $this->createDbalQueryBuilderMock([]);

        $this->connection
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findAll($this->tenantId);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findAllReturnsHydratedEntries(): void
    {
        $rows = [$this->buildRowData(1)];
        $qb = $this->createDbalQueryBuilderMock($rows);

        $this->connection
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findAll($this->tenantId);

        self::assertCount(1, $result);
        self::assertInstanceOf(TranslationEntry::class, $result[0]);
        self::assertSame('en', $result[0]->locale->value());
        self::assertSame('messages', $result[0]->domain->value());
    }

    #[Test]
    public function findAllWithLocaleFilterPassesFilter(): void
    {
        $qb = $this->createDbalQueryBuilderMock([]);

        $this->connection
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findAll($this->tenantId, ['locale' => 'en'], 1, 50);

        self::assertIsArray($result);
    }

    #[Test]
    public function findAllWithDomainFilterPassesFilter(): void
    {
        $qb = $this->createDbalQueryBuilderMock([]);

        $this->connection
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->findAll($this->tenantId, ['domain' => 'messages'], 1, 25);

        self::assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // count()
    // -----------------------------------------------------------------------

    #[Test]
    public function countReturnsIntegerCount(): void
    {
        $qb = $this->createDbalQueryBuilderMock([], 42);

        $this->connection
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->count($this->tenantId);

        self::assertSame(42, $result);
    }

    #[Test]
    public function countWithFiltersReturnsFilteredCount(): void
    {
        $qb = $this->createDbalQueryBuilderMock([], 7);

        $this->connection
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->count($this->tenantId, ['locale' => 'fr']);

        self::assertSame(7, $result);
    }

    // -----------------------------------------------------------------------
    // findMissingInLocale()
    // -----------------------------------------------------------------------

    #[Test]
    public function findMissingInLocaleReturnsEmptyArrayWhenNothingMissing(): void
    {
        $sourceLocale = LanguageCode::fromString('en');
        $targetLocale = LanguageCode::fromString('fr');

        $this->connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->repository->findMissingInLocale($this->tenantId, $sourceLocale, $targetLocale);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findMissingInLocaleReturnsMissingKeysWithDomain(): void
    {
        $sourceLocale = LanguageCode::fromString('en');
        $targetLocale = LanguageCode::fromString('fr');

        $this->connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['translation_key' => 'common.save', 'domain' => 'messages'],
                ['translation_key' => 'common.cancel', 'domain' => 'messages'],
            ]);

        $result = $this->repository->findMissingInLocale($this->tenantId, $sourceLocale, $targetLocale);

        self::assertCount(2, $result);
        self::assertSame('common.save', $result[0]['key']);
        self::assertSame('messages', $result[0]['domain']);
        self::assertSame('common.cancel', $result[1]['key']);
    }

    #[Test]
    public function findMissingInLocaleWithDomainFilterPassesDomainParam(): void
    {
        $sourceLocale = LanguageCode::fromString('en');
        $targetLocale = LanguageCode::fromString('de');

        $this->connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::stringContains('AND source.field = :domain'),
                self::arrayHasKey('domain')
            )
            ->willReturn([]);

        $result = $this->repository->findMissingInLocale(
            $this->tenantId,
            $sourceLocale,
            $targetLocale,
            $this->domain,
        );

        self::assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // getStatistics()
    // -----------------------------------------------------------------------

    #[Test]
    public function getStatisticsReturnsCorrectStructureWhenNoData(): void
    {
        // getStatistics makes two separate fetchAllAssociative calls on the connection
        $this->connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->repository->getStatistics($this->tenantId);

        self::assertArrayHasKey('byDomain', $result);
        self::assertArrayHasKey('byLocale', $result);
        self::assertArrayHasKey('overall', $result);
        self::assertSame(0, $result['overall']['totalKeys']);
        self::assertSame(0, $result['overall']['domains']);
    }

    #[Test]
    public function getStatisticsAggregatesCountsCorrectly(): void
    {
        $countRows = [
            ['domain' => 'messages', 'locale' => 'en', 'count' => '10'],
            ['domain' => 'messages', 'locale' => 'fr', 'count' => '8'],
        ];
        $totalRows = [
            ['domain' => 'messages', 'total_keys' => '10'],
        ];

        $this->connection
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($countRows, $totalRows);

        $result = $this->repository->getStatistics($this->tenantId);

        self::assertArrayHasKey('messages', $result['byDomain']);
        self::assertSame(10, $result['byDomain']['messages']['totalKeys']);
        self::assertArrayHasKey('en', $result['byDomain']['messages']['locales']);
        self::assertSame(10, $result['byDomain']['messages']['locales']['en']['translatedKeys']);
        self::assertArrayHasKey('en', $result['byLocale']);
        self::assertArrayHasKey('fr', $result['byLocale']);
        self::assertSame(10, $result['overall']['totalKeys']);
        self::assertSame(1, $result['overall']['domains']);
    }

    // -----------------------------------------------------------------------
    // Hydration via findById — null content is handled
    // -----------------------------------------------------------------------

    #[Test]
    public function findByIdHandlesNullContentAsEmptyString(): void
    {
        $row = $this->buildRowData(2);
        $row['content'] = null;

        $this->connection
            ->method('fetchAssociative')
            ->willReturn($row);

        $result = $this->repository->findById(2, $this->tenantId);

        self::assertInstanceOf(TranslationEntry::class, $result);
        self::assertSame('', $result->value->value());
    }
}
