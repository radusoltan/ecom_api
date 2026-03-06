<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Command;

use App\Shared\Infrastructure\Command\DataRetentionCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DataRetentionCommand::class)]
#[Group('unit')]
#[Group('infrastructure')]
final class DataRetentionCommandTest extends TestCase
{
    private Connection&MockObject $connection;
    private LoggerInterface&MockObject $logger;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $command = new DataRetentionCommand($this->connection, $this->logger);
        $this->tester = new CommandTester($command);
    }

    // -----------------------------------------------------------------------
    // Helper: build a Result mock that returns a single scalar from fetchOne
    // -----------------------------------------------------------------------
    private function makeResult(mixed $scalar): Result&MockObject
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn($scalar);

        return $result;
    }

    // -----------------------------------------------------------------------
    // Dry-run mode
    // -----------------------------------------------------------------------

    #[Test]
    public function itRunsInDryRunModeAndShowsRowCounts(): void
    {
        // SET ROLE + RESET ROLE
        $this->connection->expects(self::exactly(2))->method('executeStatement');

        // executeQuery: first call = table exists check, second = COUNT query per table
        // 6 policies x 2 queries each = 12 calls
        $tableExistsResult = $this->makeResult(true);
        $countResult = $this->makeResult(5);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls(
                $tableExistsResult, $countResult,   // audit_logs
                $tableExistsResult, $countResult,   // sessions
                $tableExistsResult, $countResult,   // notifications
                $tableExistsResult, $countResult,   // abandoned_carts
                $tableExistsResult, $countResult,   // orders
                $tableExistsResult, $countResult,   // analytics_price_history
            );

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('DRY RUN MODE', $this->tester->getDisplay());
        self::assertStringContainsString('that would be deleted', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Unknown policy
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureForUnknownPolicy(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeStatement'); // SET ROLE + RESET ROLE

        $exit = $this->tester->execute(['--policy' => 'nonexistent_policy']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown policy', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Specific policy
    // -----------------------------------------------------------------------

    #[Test]
    public function itRunsSpecificPolicyInDryRun(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeStatement'); // SET ROLE + RESET ROLE

        $tableExistsResult = $this->makeResult(true);
        $countResult = $this->makeResult(10);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($tableExistsResult, $countResult);

        $exit = $this->tester->execute([
            '--policy' => 'audit_logs',
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('audit_logs', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Table does not exist
    // -----------------------------------------------------------------------

    #[Test]
    public function itSkipsNonExistentTable(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeStatement'); // SET ROLE + RESET ROLE

        // All table existence checks return false except first one
        $tableNotExists = $this->makeResult(false);

        $this->connection
            ->method('executeQuery')
            ->willReturn($tableNotExists);

        $exit = $this->tester->execute(['--policy' => 'audit_logs']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('does not exist', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Zero rows to delete
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesZeroRowsToDelete(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeStatement');

        $tableExistsResult = $this->makeResult(true);
        $countResult = $this->makeResult(0);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($tableExistsResult, $countResult);

        $exit = $this->tester->execute(['--policy' => 'audit_logs']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Total rows', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Actual deletion (not dry-run)
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeletesRowsWhenNotInDryRun(): void
    {
        // executeStatement: SET ROLE, DELETE (1 batch), RESET ROLE = 3 calls
        $this->connection
            ->expects(self::exactly(3))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(
                0,  // SET ROLE
                5,  // DELETE batch
                0,  // RESET ROLE
            );

        $tableExistsResult = $this->makeResult(true);
        $countResult = $this->makeResult(5);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($tableExistsResult, $countResult);

        $exit = $this->tester->execute(['--policy' => 'audit_logs']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('deleted', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Abandoned carts policy applies extra condition
    // -----------------------------------------------------------------------

    #[Test]
    public function itAppliesExtraConditionForAbandonedCarts(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeStatement');

        $tableExistsResult = $this->makeResult(true);
        $countResult = $this->makeResult(3);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($tableExistsResult, $countResult);

        $exit = $this->tester->execute([
            '--policy' => 'abandoned_carts',
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('abandoned_carts', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Policy exception is caught and logged
    // -----------------------------------------------------------------------

    #[Test]
    public function itContinuesWhenOnePolicyThrows(): void
    {
        // SET ROLE + RESET ROLE = 2 executeStatement calls
        $this->connection->expects(self::exactly(2))->method('executeStatement');

        $this->connection
            ->method('executeQuery')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->logger->expects(self::atLeastOnce())->method('error');

        $exit = $this->tester->execute(['--policy' => 'audit_logs']);

        // Still SUCCESS because the outer loop catches per-policy errors
        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Failed to apply policy', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Multiple batches deletion
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeletesInMultipleBatches(): void
    {
        // SET ROLE, DELETE batch1 (3 rows), DELETE batch2 (2 rows), RESET ROLE = 4
        $this->connection
            ->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(
                0,  // SET ROLE
                3,  // first batch
                2,  // second batch (deletes remaining)
                0,  // RESET ROLE
            );

        $tableExistsResult = $this->makeResult(true);
        $countResult = $this->makeResult(5);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($tableExistsResult, $countResult);

        $exit = $this->tester->execute([
            '--policy' => 'audit_logs',
            '--batch-size' => '3',
        ]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Custom batch-size option
    // -----------------------------------------------------------------------

    #[Test]
    public function itRespectsCustomBatchSize(): void
    {
        $this->connection->expects(self::exactly(3))->method('executeStatement')
            ->willReturnOnConsecutiveCalls(0, 2, 0);

        $tableExistsResult = $this->makeResult(true);
        $countResult = $this->makeResult(2);

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($tableExistsResult, $countResult);

        $exit = $this->tester->execute([
            '--policy' => 'notifications',
            '--batch-size' => '500',
        ]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // All policies run by default
    // -----------------------------------------------------------------------

    #[Test]
    public function itRunsAllPoliciesWhenNoPolicySpecified(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeStatement'); // SET+RESET

        // 6 policies x (table_exists + count) = 12 executeQuery calls
        $tableNotExists = $this->makeResult(false);

        $this->connection
            ->method('executeQuery')
            ->willReturn($tableNotExists);

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
    }
}
