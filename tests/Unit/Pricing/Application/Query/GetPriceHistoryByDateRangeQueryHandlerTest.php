<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\Query\GetPriceHistoryByDateRange\GetPriceHistoryByDateRangeQuery;
use App\Pricing\Application\Query\GetPriceHistoryByDateRange\GetPriceHistoryByDateRangeQueryHandler;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetPriceHistoryByDateRangeQueryHandlerTest extends TestCase
{
    public function testItReturnsEmptyWhenNoHistory(): void
    {
        $repo = $this->createStub(PriceHistoryRepositoryInterface::class);
        $repo->method('findByDateRange')->willReturn([]);

        $handler = new GetPriceHistoryByDateRangeQueryHandler($repo);
        $result = ($handler)(new GetPriceHistoryByDateRangeQuery(
            '00000000-0000-4000-8000-000000000001',
            new \DateTimeImmutable('-30 days'),
            new \DateTimeImmutable(),
        ));

        self::assertSame([], $result);
    }
}
