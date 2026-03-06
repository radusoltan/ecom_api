<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Application\Query;

use App\Internationalization\Application\Query\GetTranslations;
use App\Internationalization\Application\Query\GetTranslationsHandler;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationEntry;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationEntryRepositoryInterface;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetTranslationsHandler::class)]
final class GetTranslationsHandlerTest extends TestCase
{
    private TranslationEntryRepositoryInterface&MockObject $repository;
    private GetTranslationsHandler $handler;
    private string $tenantIdString;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationEntryRepositoryInterface::class);
        $this->handler = new GetTranslationsHandler($this->repository);
        $this->tenantIdString = '00000000-0000-4000-8000-000000000001';
    }

    // -------
    // __invoke()
    // -------

    #[Test]
    public function itCallsFindAllWithCorrectParameters(): void
    {
        $filters = ['locale' => 'en', 'domain' => 'messages'];

        $this->repository
            ->expects(self::once())
            ->method('findAll')
            ->with(
                tenantId: self::callback(fn (TenantId $t) => $t->toString() === $this->tenantIdString),
                filters: $filters,
                page: 2,
                perPage: 25,
            )
            ->willReturn([]);

        $this->repository->method('count')->willReturn(0);

        $query = new GetTranslations(
            tenantId: $this->tenantIdString,
            filters: $filters,
            page: 2,
            perPage: 25,
        );

        ($this->handler)($query);
    }

    #[Test]
    public function itCallsCountWithCorrectTenantAndFilters(): void
    {
        $filters = ['domain' => 'validators'];

        $this->repository->method('findAll')->willReturn([]);
        $this->repository
            ->expects(self::once())
            ->method('count')
            ->with(
                self::callback(fn (TenantId $t) => $t->toString() === $this->tenantIdString),
                $filters,
            )
            ->willReturn(5);

        $query = new GetTranslations(
            tenantId: $this->tenantIdString,
            filters: $filters,
        );

        ($this->handler)($query);
    }

    #[Test]
    public function itReturnsCorrectPaginationMetadata(): void
    {
        $this->repository->method('findAll')->willReturn([]);
        $this->repository->method('count')->willReturn(150);

        $query = new GetTranslations(
            tenantId: $this->tenantIdString,
            page: 3,
            perPage: 25,
        );

        $result = ($this->handler)($query);

        self::assertSame(150, $result['total']);
        self::assertSame(3, $result['page']);
        self::assertSame(25, $result['perPage']);
        self::assertSame(6, $result['totalPages']); // ceil(150/25) = 6
    }

    #[Test]
    public function itCalculatesTotalPagesCorrectly(): void
    {
        $this->repository->method('findAll')->willReturn([]);
        $this->repository->method('count')->willReturn(51);

        $query = new GetTranslations(
            tenantId: $this->tenantIdString,
            page: 1,
            perPage: 50,
        );

        $result = ($this->handler)($query);

        self::assertSame(2, $result['totalPages']); // ceil(51/50) = 2
    }

    #[Test]
    public function itReturnsTotalPagesOfOneWhenExactFit(): void
    {
        $this->repository->method('findAll')->willReturn([]);
        $this->repository->method('count')->willReturn(50);

        $query = new GetTranslations(
            tenantId: $this->tenantIdString,
            page: 1,
            perPage: 50,
        );

        $result = ($this->handler)($query);

        self::assertSame(1, $result['totalPages']);
    }

    #[Test]
    public function itMapsTranslationEntriesToArrayFormat(): void
    {
        $tenantId = TenantId::fromString($this->tenantIdString);
        $entry = TranslationEntry::create(
            tenantId: $tenantId,
            locale: LanguageCode::fromString('en'),
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('greeting.hello'),
            value: TranslationValue::fromString('Hello'),
        );

        $this->repository->method('findAll')->willReturn([$entry]);
        $this->repository->method('count')->willReturn(1);

        $query = new GetTranslations(tenantId: $this->tenantIdString);

        $result = ($this->handler)($query);

        self::assertCount(1, $result['translations']);
        self::assertIsArray($result['translations'][0]);
        self::assertSame('en', $result['translations'][0]['locale']);
        self::assertSame('messages', $result['translations'][0]['domain']);
        self::assertSame('greeting.hello', $result['translations'][0]['key']);
        self::assertSame('Hello', $result['translations'][0]['value']);
    }

    #[Test]
    public function itReturnsExpectedResponseStructure(): void
    {
        $this->repository->method('findAll')->willReturn([]);
        $this->repository->method('count')->willReturn(0);

        $query = new GetTranslations(tenantId: $this->tenantIdString);

        $result = ($this->handler)($query);

        self::assertArrayHasKey('translations', $result);
        self::assertArrayHasKey('total', $result);
        self::assertArrayHasKey('page', $result);
        self::assertArrayHasKey('perPage', $result);
        self::assertArrayHasKey('totalPages', $result);
    }

    #[Test]
    public function itUsesDefaultPageAndPerPageFromQuery(): void
    {
        $this->repository->method('findAll')->willReturn([]);
        $this->repository->method('count')->willReturn(0);

        $query = new GetTranslations(tenantId: $this->tenantIdString);

        $result = ($this->handler)($query);

        self::assertSame(1, $result['page']);
        self::assertSame(50, $result['perPage']);
    }
}
