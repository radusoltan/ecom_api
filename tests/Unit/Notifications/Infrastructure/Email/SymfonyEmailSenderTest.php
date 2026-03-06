<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Infrastructure\Email;

use App\Notifications\Infrastructure\Email\SymfonyEmailSender;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

#[Group('notifications')]
final class SymfonyEmailSenderTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private Environment&MockObject $twig;
    private LoggerInterface&MockObject $logger;
    private SymfonyEmailSender $sender;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sender = new SymfonyEmailSender(
            $this->mailer,
            $this->twig,
            $this->logger,
            'noreply@shop.test',
        );
    }

    public function testSendEmailSuccessfully(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('exists')->willReturn(true);
        $this->twig->method('getLoader')->willReturn($loader);

        $this->mailer->expects(self::once())->method('send');
        $this->logger->expects(self::once())->method('info');

        $this->sender->send(
            'customer@test.com',
            'Order Confirmation',
            'order/order_placed',
            ['orderId' => '123'],
            'en',
        );
    }

    public function testSendEmailWithoutTextTemplate(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('exists')->willReturn(false);
        $this->twig->method('getLoader')->willReturn($loader);

        $this->mailer->expects(self::once())->method('send');

        $this->sender->send('customer@test.com', 'Subject', 'template');
    }

    public function testSendEmailTextTemplateCheckThrows(): void
    {
        $this->twig->method('getLoader')
            ->willThrowException(new \RuntimeException('Loader error'));

        $this->logger->expects(self::atLeastOnce())->method('warning');
        $this->mailer->expects(self::once())->method('send');

        $this->sender->send('customer@test.com', 'Subject', 'template');
    }

    public function testSendEmailTransportFailureRethrows(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('exists')->willReturn(false);
        $this->twig->method('getLoader')->willReturn($loader);

        $this->mailer->method('send')
            ->willThrowException(new TransportException('SMTP down'));

        $this->logger->expects(self::once())->method('error');

        $this->expectException(TransportException::class);
        $this->sender->send('customer@test.com', 'Subject', 'template');
    }

    public function testSendEmailWithCustomFrom(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('exists')->willReturn(false);
        $this->twig->method('getLoader')->willReturn($loader);

        $this->mailer->expects(self::once())->method('send');

        $this->sender->send(
            'customer@test.com',
            'Subject',
            'template',
            [],
            'fr',
            'custom@shop.test',
        );
    }

    public function testSendBatchAllSucceed(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('exists')->willReturn(false);
        $this->twig->method('getLoader')->willReturn($loader);

        $this->mailer->expects(self::exactly(3))->method('send');

        $result = $this->sender->sendBatch(
            ['a@test.com', 'b@test.com', 'c@test.com'],
            'Newsletter',
            'newsletter/weekly',
        );

        self::assertSame(3, $result['sent']);
        self::assertSame(0, $result['failed']);
        self::assertSame([], $result['errors']);
    }

    public function testSendBatchPartialFailure(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('exists')->willReturn(false);
        $this->twig->method('getLoader')->willReturn($loader);

        $callCount = 0;
        $this->mailer->method('send')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;
            if (2 === $callCount) {
                throw new TransportException('Bounce');
            }
        });

        $result = $this->sender->sendBatch(
            ['a@test.com', 'b@test.com', 'c@test.com'],
            'Newsletter',
            'newsletter/weekly',
        );

        self::assertSame(2, $result['sent']);
        self::assertSame(1, $result['failed']);
        self::assertArrayHasKey('b@test.com', $result['errors']);
    }
}
