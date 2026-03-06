<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Command;

use App\Catalog\Infrastructure\Command\BackfillJsonbTranslationsCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(BackfillJsonbTranslationsCommand::class)]
#[Group('unit')]
#[Group('infrastructure')]
final class BackfillJsonbTranslationsCommandTest extends TestCase
{
    private Connection&MockObject $connection;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $command = new BackfillJsonbTranslationsCommand($this->connection);
        $this->tester = new CommandTester($command);
    }

    // -----------------------------------------------------------------------
    // ext_translations table does not exist
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureWhenExtTranslationsTableDoesNotExist(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(false);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('ext_translations table does not exist', $this->tester->getDisplay());
    }

    #[Test]
    public function itReturnsFailureWhenCheckThrowsException(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willThrowException(new \RuntimeException('Connection error'));

        $exit = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Failed to check ext_translations', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Invalid entity type
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureForInvalidEntityType(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(true); // ext_translations exists

        $exit = $this->tester->execute(['--entity' => 'invalid_type']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Invalid entity type', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Dry-run with no entities
    // -----------------------------------------------------------------------

    #[Test]
    public function itRunsDryRunWithNoEntities(): void
    {
        // fetchOne: ext_translations exists (1st call) + 0 count for products (2nd) + 0 count for categories (3rd)
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true, // ext_translations check
                0,    // products count
                0,    // categories count
            );

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('DRY RUN MODE', $this->tester->getDisplay());
        self::assertStringContainsString('Processed 0 total records', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Products only entity type
    // -----------------------------------------------------------------------

    #[Test]
    public function itProcessesProductEntityType(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true, // ext_translations check
                0,    // products count (no entities)
            );

        $exit = $this->tester->execute(['--entity' => 'product']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Processed 0 total records', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Categories only entity type
    // -----------------------------------------------------------------------

    #[Test]
    public function itProcessesCategoryEntityType(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true, // ext_translations check
                0,    // categories count (no entities)
            );

        $exit = $this->tester->execute(['--entity' => 'category']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Processed 0 total records', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // All entities processed (product + category)
    // -----------------------------------------------------------------------

    #[Test]
    public function itProcessesAllEntityTypes(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true, // ext_translations check
                0,    // products count
                0,    // categories count
            );

        $exit = $this->tester->execute(['--entity' => 'all']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Processing catalog_products', $this->tester->getDisplay());
        self::assertStringContainsString('Processing catalog_categories', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Default entity type (all)
    // -----------------------------------------------------------------------

    #[Test]
    public function itDefaultsToAllEntities(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true, // ext_translations check
                0,    // products count
                0,    // categories count
            );

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Processing catalog_products', $this->tester->getDisplay());
        self::assertStringContainsString('Processing catalog_categories', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // No entities found message
    // -----------------------------------------------------------------------

    #[Test]
    public function itShowsNoEntitiesToProcessMessage(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true, // ext_translations check
                0,    // count for products
                0,    // count for categories
            );

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        // "No entities to process" is shown for each table with 0 rows
        self::assertStringContainsString('No entities to process', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Custom batch size option
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsCustomBatchSize(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true, // ext_translations check
                0,    // products count
                0,    // categories count
            );

        $exit = $this->tester->execute(['--batch' => '200']);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Custom tenant option
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsCustomTenantOption(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true,
                0,
                0,
            );

        $exit = $this->tester->execute(['--tenant' => '00000000-0000-4000-8000-000000000001']);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Success message includes total processed
    // -----------------------------------------------------------------------

    #[Test]
    public function itShowsBackfillCompletedMessage(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true,
                0,
                0,
            );

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Backfill completed!', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Dry-run flag in output
    // -----------------------------------------------------------------------

    #[Test]
    public function itShowsDryRunWarning(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                true,
                0,
                0,
            );

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('DRY RUN MODE', $this->tester->getDisplay());
    }
}
