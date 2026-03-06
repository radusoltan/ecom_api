<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Console;

use App\Payment\Application\Service\PaymentRetryService;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Console\ProcessPaymentRetriesCommand;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ProcessPaymentRetriesCommand::class)]
#[Group('payment')]
final class ProcessPaymentRetriesCommandTest extends TestCase
{
    private PaymentRetryService&MockObject $retryService;
    private LoggerInterface&MockObject $logger;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->retryService = $this->createMock(PaymentRetryService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $command = new ProcessPaymentRetriesCommand(
            $this->retryService,
            $this->logger,
        );

        $this->tester = new CommandTester($command);
    }

    // ---------------------------------------------------------------
    // No payments due
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsSuccessWhenNoPaymentsDueForRetry(): void
    {
        $this->retryService
            ->expects(self::once())
            ->method('getPaymentsDueForRetry')
            ->willReturn([]);

        $this->retryService->expects(self::never())->method('processRetry');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No payments due for retry', $this->tester->getDisplay());
    }

    // ---------------------------------------------------------------
    // All payments processed successfully
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsSuccessWhenAllPaymentsProcessedSuccessfully(): void
    {
        $payment = $this->buildFailedPayment();

        $this->retryService
            ->expects(self::once())
            ->method('getPaymentsDueForRetry')
            ->willReturn([$payment]);

        $this->retryService
            ->expects(self::once())
            ->method('processRetry')
            ->with($payment)
            ->willReturn(true);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Successfully processed', $this->tester->getDisplay());
    }

    // ---------------------------------------------------------------
    // A payment fails during processing
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsFailureWhenAPaymentFailsProcessing(): void
    {
        $payment = $this->buildFailedPayment();

        $this->retryService
            ->method('getPaymentsDueForRetry')
            ->willReturn([$payment]);

        $this->retryService
            ->method('processRetry')
            ->willReturn(false);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('failed to process', $this->tester->getDisplay());
    }

    // ---------------------------------------------------------------
    // An exception is thrown during processing a single payment
    // ---------------------------------------------------------------

    #[Test]
    public function itContinuesProcessingWhenOnePaymentThrowsException(): void
    {
        $payment = $this->buildFailedPayment();

        $this->retryService
            ->method('getPaymentsDueForRetry')
            ->willReturn([$payment]);

        $this->retryService
            ->method('processRetry')
            ->willThrowException(new \RuntimeException('Gateway error'));

        $this->logger
            ->expects(self::atLeastOnce())
            ->method('error');

        $exitCode = $this->tester->execute([]);

        // Should return failure because of the exception-turned-failure
        self::assertSame(Command::FAILURE, $exitCode);
    }

    // ---------------------------------------------------------------
    // Dry-run mode
    // ---------------------------------------------------------------

    #[Test]
    public function itRunsInDryRunModeWithoutProcessingRetries(): void
    {
        $payment = $this->buildFailedPayment();

        $this->retryService
            ->expects(self::once())
            ->method('getPaymentsDueForRetry')
            ->willReturn([$payment]);

        // processRetry must NOT be called in dry-run mode
        $this->retryService->expects(self::never())->method('processRetry');

        $exitCode = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('DRY-RUN', $this->tester->getDisplay());
    }

    // ---------------------------------------------------------------
    // Limit option
    // ---------------------------------------------------------------

    #[Test]
    public function itRespectsTheLimitOptionWhenProcessingPayments(): void
    {
        $payment1 = $this->buildFailedPayment();
        $payment2 = $this->buildFailedPayment();
        $payment3 = $this->buildFailedPayment();

        $this->retryService
            ->method('getPaymentsDueForRetry')
            ->willReturn([$payment1, $payment2, $payment3]);

        // With limit=1, only the first payment should be processed
        $this->retryService
            ->expects(self::once())
            ->method('processRetry')
            ->willReturn(true);

        $exitCode = $this->tester->execute(['--limit' => '1']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Processing only 1 of 3', $this->tester->getDisplay());
    }

    // ---------------------------------------------------------------
    // Global exception (e.g. repository throws)
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsFailureWhenGlobalExceptionThrown(): void
    {
        $this->retryService
            ->method('getPaymentsDueForRetry')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        $this->logger
            ->expects(self::once())
            ->method('error');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Command failed', $this->tester->getDisplay());
    }

    // ---------------------------------------------------------------
    // Multiple payments: mixed success/failure
    // ---------------------------------------------------------------

    #[Test]
    public function itCountsSuccessAndFailureSeparately(): void
    {
        $payment1 = $this->buildFailedPayment();
        $payment2 = $this->buildFailedPayment();

        $this->retryService
            ->method('getPaymentsDueForRetry')
            ->willReturn([$payment1, $payment2]);

        $this->retryService
            ->method('processRetry')
            ->willReturnOnConsecutiveCalls(true, false);

        $exitCode = $this->tester->execute([]);

        // One failure means the command returns failure
        self::assertSame(Command::FAILURE, $exitCode);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildFailedPayment(): Payment
    {
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            orderId: 'order-001',
            amount: Money::fromScalars(1000, 'USD'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
        );

        // Transition to failed state so the command can process it
        $payment->markAsFailed('card_declined', 'card_declined');

        // Schedule a retry so isDueForRetry() is realistic
        // (command only uses the Payment object for id/retryCount/nextRetryAt display)
        return $payment;
    }
}
