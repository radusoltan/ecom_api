<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\EventSubscriber;

use App\Pricing\Application\EventSubscriber\FlashSaleEndedSubscriber;
use App\Pricing\Domain\Event\FlashSaleEnded;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[CoversClass(FlashSaleEndedSubscriber::class)]
final class FlashSaleEndedSubscriberTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private FlashSaleEndedSubscriber $subscriber;
    private TenantId $tenantId;
    private FlashSaleId $flashSaleId;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new FlashSaleEndedSubscriber(logger: $this->logger);

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->flashSaleId = FlashSaleId::generate();
    }

    // -----------------------------------------------------------------------
    // getSubscribedEvents
    // -----------------------------------------------------------------------

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itSubscribesToFlashSaleEndedEvent(): void
    {
        $events = FlashSaleEndedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(FlashSaleEnded::class, $events);
        self::assertSame('onFlashSaleEnded', $events[FlashSaleEnded::class]);
    }

    #[Test]
    public function itSubscribesToExactlyOneEvent(): void
    {
        $events = FlashSaleEndedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -----------------------------------------------------------------------
    // onFlashSaleEnded — success path
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInfoWhenFlashSaleEnded(): void
    {
        $event = new FlashSaleEnded(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Flash sale ended',
                self::anything(),
            );

        $this->subscriber->onFlashSaleEnded($event);
    }

    #[Test]
    public function itIncludesFlashSaleIdInLogContext(): void
    {
        $event = new FlashSaleEnded(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('flash_sale_id', $context);
                    self::assertSame($this->flashSaleId->toString(), $context['flash_sale_id']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleEnded($event);
    }

    #[Test]
    public function itIncludesTenantIdInLogContext(): void
    {
        $event = new FlashSaleEnded(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context): bool {
                    self::assertArrayHasKey('tenant_id', $context);
                    self::assertSame($this->tenantId->toString(), $context['tenant_id']);

                    return true;
                }),
            );

        $this->subscriber->onFlashSaleEnded($event);
    }

    #[Test]
    public function itLogContextContainsBothRequiredKeys(): void
    {
        $event = new FlashSaleEnded(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            occurredAt: new \DateTimeImmutable(),
        );

        $capturedContext = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onFlashSaleEnded($event);

        self::assertArrayHasKey('flash_sale_id', $capturedContext);
        self::assertArrayHasKey('tenant_id', $capturedContext);
        self::assertSame($this->flashSaleId->toString(), $capturedContext['flash_sale_id']);
        self::assertSame($this->tenantId->toString(), $capturedContext['tenant_id']);
    }

    // -----------------------------------------------------------------------
    // Multiple flash sales — different IDs logged correctly
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsCorrectFlashSaleIdForDistinctSales(): void
    {
        $flashSaleId1 = FlashSaleId::generate();
        $flashSaleId2 = FlashSaleId::generate();

        $capturedIds = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedIds): void {
                $capturedIds[] = $context['flash_sale_id'];
            });

        $this->subscriber->onFlashSaleEnded(new FlashSaleEnded(
            flashSaleId: $flashSaleId1,
            tenantId: $this->tenantId,
            occurredAt: new \DateTimeImmutable(),
        ));

        $this->subscriber->onFlashSaleEnded(new FlashSaleEnded(
            flashSaleId: $flashSaleId2,
            tenantId: $this->tenantId,
            occurredAt: new \DateTimeImmutable(),
        ));

        self::assertCount(2, $capturedIds);
        self::assertSame($flashSaleId1->toString(), $capturedIds[0]);
        self::assertSame($flashSaleId2->toString(), $capturedIds[1]);
    }

    // -----------------------------------------------------------------------
    // Error resilience
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNotThrowDuringNormalExecution(): void
    {
        $event = new FlashSaleEnded(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            occurredAt: new \DateTimeImmutable(),
        );

        $this->logger->method('info');

        // Must not throw
        $this->subscriber->onFlashSaleEnded($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itHandlesPastOccurredAtDate(): void
    {
        $pastDate = new \DateTimeImmutable('2020-01-15 14:30:00');

        $event = new FlashSaleEnded(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            occurredAt: $pastDate,
        );

        $this->logger->expects(self::once())->method('info');

        $this->subscriber->onFlashSaleEnded($event);
    }
}
