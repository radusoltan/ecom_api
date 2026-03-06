<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Search\Application\Query\AutocompleteProducts;
use App\Search\Application\Query\AutocompleteProductsHandler;
use App\Search\Domain\Model\ProductSearchHit;
use App\Search\Domain\Service\SearchServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutocompleteProductsHandler::class)]
final class AutocompleteProductsHandlerTest extends TestCase
{
    private SearchServiceInterface $searchService;
    private AutocompleteProductsHandler $handler;

    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchServiceInterface::class);
        $this->handler = new AutocompleteProductsHandler($this->searchService);
    }

    // -------------------------------------------------------
    // Happy path
    // -------------------------------------------------------

    #[Test]
    public function itReturnsHitsFromSearchService(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $command = new AutocompleteProducts(
            tenantId: $tenantId,
            query: 'lap',
            locale: 'en_US',
            limit: 5,
        );

        $hits = [
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000001'),
                sku: 'LAP-000001',
                name: 'Laptop Pro',
                description: null,
                priceInCents: 99999,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 1.0,
            ),
        ];

        $this->searchService
            ->expects(self::once())
            ->method('autocomplete')
            ->with(
                query: 'lap',
                tenantId: $tenantId,
                locale: 'en_US',
                limit: 5,
            )
            ->willReturn($hits);

        $result = ($this->handler)($command);

        self::assertCount(1, $result);
        self::assertSame('LAP-000001', $result[0]->sku);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoMatches(): void
    {
        $command = new AutocompleteProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'xyzzy',
            locale: 'en_US',
            limit: 5,
        );

        $this->searchService->method('autocomplete')->willReturn([]);

        $result = ($this->handler)($command);

        self::assertSame([], $result);
    }

    #[Test]
    public function itForwardsCustomLimitToService(): void
    {
        $command = new AutocompleteProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'cam',
            locale: 'en_US',
            limit: 10,
        );

        $this->searchService
            ->expects(self::once())
            ->method('autocomplete')
            ->with(
                query: 'cam',
                tenantId: '00000000-0000-4000-8000-000000000001',
                locale: 'en_US',
                limit: 10,
            )
            ->willReturn([]);

        ($this->handler)($command);
    }

    // -------------------------------------------------------
    // AutocompleteProducts DTO validation
    // -------------------------------------------------------

    #[Test]
    public function itThrowsWhenLimitIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Autocomplete limit must be between 1 and 20');

        new AutocompleteProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'lap',
            locale: 'en',
            limit: 0,
        );
    }

    #[Test]
    public function itThrowsWhenLimitExceedsTwenty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Autocomplete limit must be between 1 and 20');

        new AutocompleteProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'lap',
            locale: 'en',
            limit: 21,
        );
    }

    #[Test]
    public function itAcceptsLimitAtBoundaries(): void
    {
        $min = new AutocompleteProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'lap',
            locale: 'en',
            limit: 1,
        );
        $max = new AutocompleteProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'lap',
            locale: 'en',
            limit: 20,
        );

        self::assertSame(1, $min->limit);
        self::assertSame(20, $max->limit);
    }
}
