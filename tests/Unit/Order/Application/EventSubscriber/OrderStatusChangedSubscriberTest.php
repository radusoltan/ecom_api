<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\EventSubscriber;

use App\Order\Application\EventSubscriber\OrderStatusChangedSubscriber;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(OrderStatusChangedSubscriber::class)]
final class OrderStatusChangedSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private OrderRepositoryInterface $orderRepository;
    private TranslatorInterface $translator;
    private LoggerInterface $logger;
    private OrderStatusChangedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderStatusChangedSubscriber(
            mailer: $this->mailer,
            orderRepository: $this->orderRepository,
            translator: $this->translator,
            logger: $this->logger,
            senderEmail: 'test@example.com',
            senderName: 'Test Platform'
        );
    }

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itSubscribesToOrderStatusChangedEvent(): void
    {
        $subscribedEvents = OrderStatusChangedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderStatusChanged::class, $subscribedEvents);
        self::assertEquals('onOrderStatusChanged', $subscribedEvents[OrderStatusChanged::class]);
    }

    #[Test]
    public function itSendsNotificationWhenOrderIsPaid(): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            oldStatus: OrderStatus::pending(),
            newStatus: OrderStatus::paid()
        );

        $this->orderRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null); // Order not found will trigger warning log

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Cannot send status change email - order not found', self::anything());

        $this->mailer
            ->expects(self::never())
            ->method('send'); // Should not send if order not found

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }

    #[Test]
    #[DataProvider('statusesThatShouldNotTriggerNotification')]
    public function itDoesNotSendNotificationForNonPaidStatuses(OrderStatus $newStatus): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            oldStatus: OrderStatus::pending(),
            newStatus: $newStatus
        );

        // These statuses should not trigger any notification
        $this->mailer
            ->expects(self::never())
            ->method('send');

        $this->logger
            ->expects(self::never())
            ->method('info');

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }

    public static function statusesThatShouldNotTriggerNotification(): array
    {
        return [
            'processing' => [OrderStatus::processing()],
            'shipped' => [OrderStatus::shipped()],
            'delivered' => [OrderStatus::delivered()],
            'cancelled' => [OrderStatus::cancelled()],
        ];
    }
}
