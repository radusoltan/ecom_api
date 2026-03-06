<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\EventSubscriber;

use App\Cart\Application\EventSubscriber\CartAbandonedSubscriber;
use App\Cart\Domain\Event\CartAbandoned;
use App\Cart\Domain\Model\CartId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(CartAbandonedSubscriber::class)]
final class CartAbandonedSubscriberTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private CartAbandonedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturn('Your cart is waiting!');

        $this->subscriber = new CartAbandonedSubscriber(
            mailer: $this->mailer,
            translator: $this->translator,
            logger: $this->logger,
            senderEmail: 'carts@example.com',
            senderName: 'Test Store',
            defaultLocale: 'en',
            storefrontUrl: 'http://localhost:3000',
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToCartAbandonedEvent(): void
    {
        $events = CartAbandonedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(CartAbandoned::class, $events);
        self::assertSame('onCartAbandoned', $events[CartAbandoned::class]);
    }

    #[Test]
    public function getSubscribedEventsReturnsArray(): void
    {
        $events = CartAbandonedSubscriber::getSubscribedEvents();

        self::assertIsArray($events);
        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // Happy path: email is sent
    // -------------------------------------------------------

    #[Test]
    public function itSendsEmailOnCartAbandoned(): void
    {
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'user@example.com',
            amount: 4999,
            currency: 'EUR',
            itemCount: 3,
        );

        $this->mailer->expects(self::once())->method('send');
        $this->logger->expects(self::once())->method('info');

        $this->subscriber->onCartAbandoned($event);
    }

    #[Test]
    public function itLogsSuccessInfoAfterSendingEmail(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'buyer@example.com',
            amount: 1000,
            currency: 'USD',
            itemCount: 1,
            cartId: $cartId,
            tenantId: $tenantId,
        );

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Cart abandonment email sent',
                self::callback(function (array $context) use ($cartId, $tenantId): bool {
                    return $context['cartId'] === $cartId->toString()
                        && 'buyer@example.com' === $context['customerEmail']
                        && $context['tenantId'] === $tenantId->toString()
                        && 1 === $context['itemCount'];
                })
            );

        $this->mailer->method('send');

        $this->subscriber->onCartAbandoned($event);
    }

    // -------------------------------------------------------
    // Error handling: mailer throws, no exception propagated
    // -------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenMailerFails(): void
    {
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'user@example.com',
            amount: 2500,
            currency: 'EUR',
            itemCount: 2,
        );

        $this->mailer->method('send')->willThrowException(new \RuntimeException('SMTP connection failed'));

        // Must not throw
        $this->subscriber->onCartAbandoned($event);

        // Passes if no exception is raised
        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenMailerFails(): void
    {
        $cartId = CartId::generate();
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'fail@example.com',
            amount: 1500,
            currency: 'EUR',
            itemCount: 1,
            cartId: $cartId,
        );

        $this->mailer->method('send')->willThrowException(new \RuntimeException('Mailer down'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to send cart abandonment email',
                self::callback(function (array $context): bool {
                    return 'fail@example.com' === $context['customerEmail']
                        && 'Mailer down' === $context['error'];
                })
            );

        $this->subscriber->onCartAbandoned($event);
    }

    #[Test]
    public function itDoesNotLogSuccessWhenMailerFails(): void
    {
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'user@example.com',
            amount: 500,
            currency: 'EUR',
            itemCount: 1,
        );

        $this->mailer->method('send')->willThrowException(new \RuntimeException('Connection refused'));

        $this->logger->expects(self::never())->method('info');

        $this->subscriber->onCartAbandoned($event);
    }

    // -------------------------------------------------------
    // Cart total conversion: minor units are rendered correctly
    // -------------------------------------------------------

    #[Test]
    public function itHandlesZeroTotalCart(): void
    {
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'user@example.com',
            amount: 0,
            currency: 'EUR',
            itemCount: 1,
        );

        $this->mailer->expects(self::once())->method('send');

        $this->subscriber->onCartAbandoned($event);
    }

    #[Test]
    public function itHandlesLargeTotalCart(): void
    {
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'vip@example.com',
            amount: 1_000_000_00, // 1,000,000.00 in minor units
            currency: 'USD',
            itemCount: 25,
        );

        $this->mailer->expects(self::once())->method('send');

        $this->subscriber->onCartAbandoned($event);
    }

    // -------------------------------------------------------
    // Translator is called for email subject
    // -------------------------------------------------------

    #[Test]
    public function itTranslatesEmailSubject(): void
    {
        $event = $this->buildCartAbandonedEvent(
            customerEmail: 'user@example.com',
            amount: 999,
            currency: 'EUR',
            itemCount: 1,
        );

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('emails.cart.abandoned.title', [], 'emails', 'en')
            ->willReturn('Your cart is waiting!');

        $this->mailer->method('send');

        $this->subscriber->onCartAbandoned($event);
    }

    // -------------------------------------------------------
    // Helper
    // -------------------------------------------------------

    private function buildCartAbandonedEvent(
        string $customerEmail,
        int $amount,
        string $currency,
        int $itemCount,
        ?CartId $cartId = null,
        ?TenantId $tenantId = null,
    ): CartAbandoned {
        return new CartAbandoned(
            cartId: $cartId ?? CartId::generate(),
            tenantId: $tenantId ?? TenantId::generate(),
            customerId: CustomerId::generate(),
            customerEmail: $customerEmail,
            cartTotal: Money::fromScalars($amount, $currency),
            itemCount: $itemCount,
            abandonedAt: new \DateTimeImmutable('2026-01-10 08:00:00'),
        );
    }
}
