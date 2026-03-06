<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\ImportCustomers\ImportCustomersCommand;
use App\Customer\Application\Command\ImportCustomers\ImportCustomersCommandHandler;
use App\Customer\Application\DTO\ImportResult;
use App\Customer\Application\Service\CustomerImportService;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ImportCustomersCommandHandler::class)]
final class ImportCustomersCommandHandlerTest extends TestCase
{
    private CustomerImportService $importService;
    private LoggerInterface $logger;
    private ImportCustomersCommandHandler $handler;

    protected function setUp(): void
    {
        $this->importService = $this->createMock(CustomerImportService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new ImportCustomersCommandHandler($this->importService, $this->logger);
    }

    #[Test]
    public function itImportsFromCsvFormat(): void
    {
        $tenantId = TenantId::generate();
        $expectedResult = new ImportResult(totalRows: 5, imported: 5, updated: 0, skipped: 0, errors: []);

        $this->importService
            ->expects(self::once())
            ->method('importFromCsv')
            ->with(
                tenantId: $tenantId,
                content: 'email,first_name,last_name',
                updateExisting: false
            )
            ->willReturn($expectedResult);

        $this->importService
            ->expects(self::never())
            ->method('importFromJson');

        $command = new ImportCustomersCommand(
            tenantId: $tenantId,
            format: 'csv',
            content: 'email,first_name,last_name',
            updateExisting: false
        );

        $result = ($this->handler)($command);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function itImportsFromJsonFormat(): void
    {
        $tenantId = TenantId::generate();
        $expectedResult = new ImportResult(totalRows: 3, imported: 2, updated: 1, skipped: 0, errors: []);

        $this->importService
            ->expects(self::once())
            ->method('importFromJson')
            ->with(
                tenantId: $tenantId,
                content: '[{"email":"a@b.com"}]',
                updateExisting: true
            )
            ->willReturn($expectedResult);

        $this->importService
            ->expects(self::never())
            ->method('importFromCsv');

        $command = new ImportCustomersCommand(
            tenantId: $tenantId,
            format: 'json',
            content: '[{"email":"a@b.com"}]',
            updateExisting: true
        );

        $result = ($this->handler)($command);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function itLogsInfoOnSuccess(): void
    {
        $tenantId = TenantId::generate();
        $expectedResult = new ImportResult(totalRows: 2, imported: 2, updated: 0, skipped: 0, errors: []);

        $this->importService
            ->method('importFromCsv')
            ->willReturn($expectedResult);

        $this->logger
            ->expects(self::atLeast(2))
            ->method('info');

        $command = new ImportCustomersCommand(
            tenantId: $tenantId,
            format: 'csv',
            content: 'email,first_name,last_name'
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itLogsErrorAndRethrowsOnException(): void
    {
        $tenantId = TenantId::generate();

        $this->importService
            ->method('importFromCsv')
            ->willThrowException(new \RuntimeException('Parse error'));

        $this->logger
            ->expects(self::once())
            ->method('error');

        $command = new ImportCustomersCommand(
            tenantId: $tenantId,
            format: 'csv',
            content: 'invalid,csv,data'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Parse error');

        ($this->handler)($command);
    }
}
