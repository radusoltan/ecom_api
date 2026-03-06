<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\Query\GetActivePromotions\GetActivePromotionsQuery;
use App\Pricing\Application\Query\GetActivePromotions\GetActivePromotionsQueryHandler;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetActivePromotionsQueryHandlerTest extends TestCase
{
    public function testItReturnsActivePromotions(): void
    {
        $repo = $this->createStub(PromotionRepositoryInterface::class);
        $repo->method('findActivePromotions')->willReturn([]);

        $handler = new GetActivePromotionsQueryHandler($repo);
        $result = ($handler)(new GetActivePromotionsQuery(TenantId::generate()));

        self::assertSame([], $result);
    }
}
