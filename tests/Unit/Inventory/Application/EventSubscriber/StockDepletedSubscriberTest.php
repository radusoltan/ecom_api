<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\EventSubscriber\StockDepletedSubscriber;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class StockDepletedSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private StockDepletedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new StockDepletedSubscriber(
            $this->mailer,
            $this->logger,
            'inventory@test.local',
            'alerts@test.local',
            'Test Alerts'
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = StockDepletedSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(StockDepleted::class, $events);
        $this->assertEquals('onStockDepleted', $events[StockDepleted::class]);
    }

    public function testOnStockDepletedSendsEmail(): void
    {
        $event = new StockDepleted(
            StockItemId::generate(),
            ProductId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(5),
            Quantity::fromInt(20),
            new \DateTimeImmutable()
        );

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($event) {
                $toAddresses = $email->getTo();
                $this->assertCount(1, $toAddresses);
                $this->assertEquals('inventory@test.local', $toAddresses[0]->getAddress());

                $fromAddresses = $email->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals('alerts@test.local', $fromAddresses[0]->getAddress());

                $this->assertStringContainsString('Low Stock Alert', $email->getSubject());
                $this->assertStringContainsString($event->productId->toString(), $email->getSubject());

                return true;
            }));

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Low stock alert sent',
                $this->callback(function ($context) use ($event) {
                    $this->assertEquals($event->stockItemId->toString(), $context['stockItemId']);
                    $this->assertEquals($event->productId->toString(), $context['productId']);
                    $this->assertEquals($event->warehouseId->toString(), $context['warehouseId']);
                    $this->assertEquals(5, $context['availableQuantity']);
                    $this->assertEquals(20, $context['threshold']);

                    return true;
                })
            );

        $this->subscriber->onStockDepleted($event);
    }

    public function testOnStockDepletedLogsErrorWhenEmailFails(): void
    {
        $event = new StockDepleted(
            StockItemId::generate(),
            ProductId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(5),
            Quantity::fromInt(20),
            new \DateTimeImmutable()
        );

        $exception = new \RuntimeException('SMTP server unavailable');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send low stock alert',
                $this->callback(function ($context) use ($event, $exception) {
                    $this->assertEquals($event->stockItemId->toString(), $context['stockItemId']);
                    $this->assertEquals($event->productId->toString(), $context['productId']);
                    $this->assertEquals($exception->getMessage(), $context['error']);
                    $this->assertArrayHasKey('trace', $context);

                    return true;
                })
            );

        // Should not throw exception
        $this->subscriber->onStockDepleted($event);
    }

    public function testEmailContainsRequiredInformation(): void
    {
        $stockItemId = StockItemId::generate();
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::generate();

        $event = new StockDepleted(
            $stockItemId,
            $productId,
            $warehouseId,
            $tenantId,
            Quantity::fromInt(3),
            Quantity::fromInt(15),
            new \DateTimeImmutable()
        );

        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->subscriber->onStockDepleted($event);

        $this->assertNotNull($sentEmail);

        // Check HTML body
        $htmlBody = $sentEmail->getHtmlBody();
        $this->assertStringContainsString($stockItemId->toString(), $htmlBody);
        $this->assertStringContainsString($productId->toString(), $htmlBody);
        $this->assertStringContainsString($warehouseId->toString(), $htmlBody);
        $this->assertStringContainsString($tenantId->toString(), $htmlBody);
        $this->assertStringContainsString('3 units', $htmlBody);
        $this->assertStringContainsString('15 units', $htmlBody);

        // Check text body
        $textBody = $sentEmail->getTextBody();
        $this->assertStringContainsString($stockItemId->toString(), $textBody);
        $this->assertStringContainsString($productId->toString(), $textBody);
        $this->assertStringContainsString('3 units', $textBody);
        $this->assertStringContainsString('15 units', $textBody);
    }
}
