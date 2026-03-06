<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Infrastructure\Console;

use App\Cart\Application\Service\CartAbandonmentService;
use App\Cart\Infrastructure\Console\ProcessAbandonedCartsCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ProcessAbandonedCartsCommand::class)]
#[Group('unit')]
#[Group('infrastructure')]
final class ProcessAbandonedCartsCommandTest extends TestCase
{
    private CartAbandonmentService&MockObject $abandonmentService;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->abandonmentService = $this->createMock(CartAbandonmentService::class);

        $command = new ProcessAbandonedCartsCommand($this->abandonmentService);
        $this->tester = new CommandTester($command);
    }

    // -----------------------------------------------------------------------
    // Dry-run mode
    // -----------------------------------------------------------------------

    #[Test]
    public function itRunsDryRunWithoutCallingService(): void
    {
        $this->abandonmentService->expects(self::never())->method('processAbandonedCarts');

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('DRY-RUN', $this->tester->getDisplay());
    }

    #[Test]
    public function itShowsDryRunStats(): void
    {
        $this->abandonmentService->expects(self::never())->method('processAbandonedCarts');

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $this->tester->getDisplay();
        self::assertStringContainsString('Processed', $display);
        self::assertStringContainsString('Emails Sent', $display);
    }

    // -----------------------------------------------------------------------
    // Normal mode — no carts found
    // -----------------------------------------------------------------------

    #[Test]
    public function itSucceedsWhenNoAbandonedCarts(): void
    {
        $this->abandonmentService
            ->expects(self::once())
            ->method('processAbandonedCarts')
            ->willReturn(['processed' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => 0]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('No abandoned carts found', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Normal mode — carts processed successfully
    // -----------------------------------------------------------------------

    #[Test]
    public function itSucceedsWithProcessedCarts(): void
    {
        $this->abandonmentService
            ->expects(self::once())
            ->method('processAbandonedCarts')
            ->willReturn(['processed' => 5, 'sent' => 4, 'skipped' => 1, 'errors' => 0]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $this->tester->getDisplay();
        self::assertStringContainsString('Successfully processed 5 abandoned cart(s)', $display);
        self::assertStringContainsString('Sent 4 email(s)', $display);
    }

    // -----------------------------------------------------------------------
    // Errors in processing
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureWhenErrorsOccur(): void
    {
        $this->abandonmentService
            ->expects(self::once())
            ->method('processAbandonedCarts')
            ->willReturn(['processed' => 3, 'sent' => 2, 'skipped' => 0, 'errors' => 1]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('1 cart(s) encountered errors', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Service throws exception
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesServiceException(): void
    {
        $this->abandonmentService
            ->expects(self::once())
            ->method('processAbandonedCarts')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        $exit = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Failed to process abandoned carts', $this->tester->getDisplay());
        self::assertStringContainsString('DB connection failed', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Verbose output shows stack trace on exception
    // -----------------------------------------------------------------------

    #[Test]
    public function itShowsTraceInVerboseModeOnException(): void
    {
        $this->abandonmentService
            ->expects(self::once())
            ->method('processAbandonedCarts')
            ->willThrowException(new \RuntimeException('Fatal error'));

        $exit = $this->tester->execute([], ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::FAILURE, $exit);
    }

    // -----------------------------------------------------------------------
    // Stats table output
    // -----------------------------------------------------------------------

    #[Test]
    public function itDisplaysResultsTable(): void
    {
        $this->abandonmentService
            ->method('processAbandonedCarts')
            ->willReturn(['processed' => 10, 'sent' => 8, 'skipped' => 2, 'errors' => 0]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $this->tester->getDisplay();
        self::assertStringContainsString('Processed', $display);
        self::assertStringContainsString('Emails Sent', $display);
        self::assertStringContainsString('Skipped', $display);
        self::assertStringContainsString('Errors', $display);
    }

    // -----------------------------------------------------------------------
    // Duration is shown
    // -----------------------------------------------------------------------

    #[Test]
    public function itDisplaysDuration(): void
    {
        $this->abandonmentService
            ->method('processAbandonedCarts')
            ->willReturn(['processed' => 1, 'sent' => 1, 'skipped' => 0, 'errors' => 0]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Completed in', $this->tester->getDisplay());
        self::assertStringContainsString('seconds', $this->tester->getDisplay());
    }
}
