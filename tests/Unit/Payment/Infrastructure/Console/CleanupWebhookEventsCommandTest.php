<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Console;

use App\Payment\Domain\Repository\WebhookEventRepositoryInterface;
use App\Payment\Infrastructure\Console\CleanupWebhookEventsCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupWebhookEventsCommandTest extends TestCase
{
    private WebhookEventRepositoryInterface&MockObject $repository;
    private LoggerInterface&MockObject $logger;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WebhookEventRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $command = new CleanupWebhookEventsCommand(
            $this->repository,
            $this->logger,
        );

        $this->commandTester = new CommandTester($command);
    }

    #[Test]
    public function cleanupDeletesOldEntries(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $date): bool {
                $expectedDate = new \DateTimeImmutable('-30 days');

                // Allow 5 seconds tolerance
                return abs($date->getTimestamp() - $expectedDate->getTimestamp()) < 5;
            }))
            ->willReturn(42);

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Deleted 42 webhook event record(s)', $this->commandTester->getDisplay());
    }

    #[Test]
    public function cleanupPreservesRecentEntries(): void
    {
        // With default 30 days retention, only old entries are deleted
        $this->repository
            ->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $date): bool {
                $now = new \DateTimeImmutable();
                // The cutoff should be approximately 30 days ago, not recent
                $daysDiff = (int) $now->diff($date)->format('%a');

                return $daysDiff >= 29 && $daysDiff <= 31;
            }))
            ->willReturn(0);

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Deleted 0 webhook event record(s)', $this->commandTester->getDisplay());
    }

    #[Test]
    public function cleanupRespectsCustomRetentionDays(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $date): bool {
                $expectedDate = new \DateTimeImmutable('-60 days');

                return abs($date->getTimestamp() - $expectedDate->getTimestamp()) < 5;
            }))
            ->willReturn(10);

        $this->commandTester->execute(['--retention-days' => 60]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('60 days', $this->commandTester->getDisplay());
    }

    #[Test]
    public function dryRunDoesNotDelete(): void
    {
        $this->repository
            ->expects($this->never())
            ->method('deleteOlderThan');

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('DRY-RUN', $this->commandTester->getDisplay());
    }

    #[Test]
    public function invalidRetentionDaysReturnsFailed(): void
    {
        $this->repository
            ->expects($this->never())
            ->method('deleteOlderThan');

        $this->commandTester->execute(['--retention-days' => 0]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('at least 1', $this->commandTester->getDisplay());
    }
}
