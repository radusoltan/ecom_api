<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\MessageHandler;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\Message\ActivateFlashSaleMessage;
use App\Pricing\Application\Message\DeactivateFlashSaleMessage;
use App\Pricing\Application\MessageHandler\ActivateFlashSaleMessageHandler;
use App\Pricing\Application\MessageHandler\DeactivateFlashSaleMessageHandler;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class FlashSaleMessageHandlersTest extends TestCase
{
    public function testActivateHandlerActivatesFlashSale(): void
    {
        $tenantId = TenantId::generate();
        $flashSaleId = FlashSaleId::generate();
        $flashSale = FlashSale::create(
            $flashSaleId, $tenantId, 'Summer Sale',
            [ProductId::generate()], Discount::percentage(20.0),
            new \DateTimeImmutable('-1 hour'), new \DateTimeImmutable('+1 hour'),
        );

        $repo = $this->createMock(FlashSaleRepositoryInterface::class);
        $repo->method('findById')->willReturn($flashSale);
        $repo->expects($this->once())->method('save');

        $handler = new ActivateFlashSaleMessageHandler($repo, new NullLogger());
        ($handler)(new ActivateFlashSaleMessage($flashSaleId->toString(), $tenantId->toString()));

        self::assertTrue($flashSale->isActive());
    }

    public function testActivateHandlerSilentlyReturnsWhenNotFound(): void
    {
        $repo = $this->createStub(FlashSaleRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new ActivateFlashSaleMessageHandler($repo, new NullLogger());
        ($handler)(new ActivateFlashSaleMessage(FlashSaleId::generate()->toString(), TenantId::generate()->toString()));

        // No exception = success (silently returns)
        $this->addToAssertionCount(1);
    }

    public function testDeactivateHandlerEndsFlashSale(): void
    {
        $tenantId = TenantId::generate();
        $flashSaleId = FlashSaleId::generate();
        $flashSale = FlashSale::create(
            $flashSaleId, $tenantId, 'Summer Sale',
            [ProductId::generate()], Discount::percentage(20.0),
            new \DateTimeImmutable('-2 hours'), new \DateTimeImmutable('+1 hour'),
        );
        $flashSale->activate();

        $repo = $this->createMock(FlashSaleRepositoryInterface::class);
        $repo->method('findById')->willReturn($flashSale);
        $repo->expects($this->once())->method('save');

        $handler = new DeactivateFlashSaleMessageHandler($repo, new NullLogger());
        ($handler)(new DeactivateFlashSaleMessage($flashSaleId->toString(), $tenantId->toString()));

        self::assertTrue($flashSale->isEnded());
    }

    public function testDeactivateHandlerSilentlyReturnsWhenNotFound(): void
    {
        $repo = $this->createStub(FlashSaleRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new DeactivateFlashSaleMessageHandler($repo, new NullLogger());
        ($handler)(new DeactivateFlashSaleMessage(FlashSaleId::generate()->toString(), TenantId::generate()->toString()));

        $this->addToAssertionCount(1);
    }
}
