<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\Query\GetLowestPriceInLastDays\GetLowestPriceInLastDaysQuery;
use App\Pricing\Application\Query\GetLowestPriceInLastDays\GetLowestPriceInLastDaysQueryHandler;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetLowestPriceInLastDaysQueryHandlerTest extends TestCase
{
    public function testItReturnsNullWhenNoPriceHistory(): void
    {
        $repo = $this->createStub(PriceHistoryRepositoryInterface::class);
        $repo->method('getLowestPriceInLastDays')->willReturn(null);

        $handler = new GetLowestPriceInLastDaysQueryHandler($repo);
        $result = ($handler)(new GetLowestPriceInLastDaysQuery(\App\Catalog\Domain\Model\ProductId::generate()->toString(), '00000000-0000-4000-8000-000000000001', 30));

        self::assertNull($result);
    }
}
