<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command\ImportPriceList;

use App\Pricing\Application\Command\ImportPriceList\ImportPriceListCommand;
use App\Pricing\Application\Command\ImportPriceList\ImportPriceListHandler;
use App\Pricing\Application\Service\ImportValidationService;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ImportPriceListHandler::class)]
final class ImportPriceListHandlerTest extends TestCase
{
    private PriceListRepositoryInterface&MockObject $repository;
    private ImportValidationService $validationService;
    private ImportPriceListHandler $handler;
    private TenantId $tenantId;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PriceListRepositoryInterface::class);
        $this->validationService = new ImportValidationService();

        $this->handler = new ImportPriceListHandler(
            $this->repository,
            $this->validationService,
            new NullLogger(),
        );

        $this->tenantId = TenantId::fromString(self::TENANT_ID);
    }

    // -----------------------------------------------------------------------
    // Empty input
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsZeroCountsForEmptyRows(): void
    {
        $command = new ImportPriceListCommand($this->tenantId, []);

        $result = ($this->handler)($command);

        self::assertSame(0, $result->totalRows);
        self::assertSame(0, $result->successCount);
        self::assertSame(0, $result->errorCount);
        self::assertSame(0, $result->createdCount);
        self::assertSame(0, $result->updatedCount);
        self::assertTrue($result->isSuccessful());
    }

    // -----------------------------------------------------------------------
    // Create new PriceList
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesNewPriceListFromValidRow(): void
    {
        $this->repository->method('findByTenant')->willReturn([]);
        $this->repository->expects(self::once())->method('save');

        $command = new ImportPriceListCommand($this->tenantId, [
            $this->makeValidRow('Summer Sale'),
        ]);

        $result = ($this->handler)($command);

        self::assertSame(1, $result->totalRows);
        self::assertSame(1, $result->successCount);
        self::assertSame(1, $result->createdCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(0, $result->errorCount);
    }

    #[Test]
    public function itCreatesMultiplePriceListsFromDistinctNameRows(): void
    {
        $this->repository->method('findByTenant')->willReturn([]);
        $this->repository->expects(self::exactly(2))->method('save');

        $command = new ImportPriceListCommand($this->tenantId, [
            $this->makeValidRow('List A'),
            $this->makeValidRow('List B'),
        ]);

        $result = ($this->handler)($command);

        self::assertSame(2, $result->createdCount);
    }

    #[Test]
    public function itGroupsRowsByPriceListName(): void
    {
        // Two rows with same name => one PriceList created
        $this->repository->method('findByTenant')->willReturn([]);
        $this->repository->expects(self::once())->method('save');

        $command = new ImportPriceListCommand($this->tenantId, [
            $this->makeValidRow('Grouped List'),
            $this->makeValidRow('Grouped List'),
        ]);

        $result = ($this->handler)($command);

        self::assertSame(1, $result->createdCount);
        self::assertSame(2, $result->successCount);
    }

    // -----------------------------------------------------------------------
    // Update existing PriceList
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesExistingPriceListWhenUpdateExistingIsTrue(): void
    {
        $existing = $this->makePriceListMock('Existing List');
        $this->repository->method('findByTenant')->willReturn([$existing]);
        $this->repository->expects(self::once())->method('save');

        $command = new ImportPriceListCommand(
            $this->tenantId,
            [$this->makeValidRow('Existing List')],
            true
        );

        $result = ($this->handler)($command);

        self::assertSame(0, $result->createdCount);
        self::assertSame(1, $result->updatedCount);
        self::assertSame(0, $result->errorCount);
    }

    #[Test]
    public function itReturnsErrorWhenPriceListExistsAndUpdateExistingIsFalse(): void
    {
        $existing = $this->makePriceListMock('Existing List');
        $this->repository->method('findByTenant')->willReturn([$existing]);
        $this->repository->expects(self::never())->method('save');

        $command = new ImportPriceListCommand(
            $this->tenantId,
            [$this->makeValidRow('Existing List')],
            false
        );

        $result = ($this->handler)($command);

        self::assertSame(0, $result->createdCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(1, $result->errorCount);
        self::assertNotEmpty($result->errors);
    }

    // -----------------------------------------------------------------------
    // Validation errors
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsErrorForRowWithMissingName(): void
    {
        $this->repository->method('findByTenant')->willReturn([]);

        $command = new ImportPriceListCommand($this->tenantId, [
            ['priority' => 100], // missing 'name'
        ]);

        $result = ($this->handler)($command);

        self::assertSame(1, $result->errorCount);
        self::assertSame(0, $result->createdCount);
    }

    #[Test]
    public function itReturnsErrorForRowWithInvalidPriority(): void
    {
        $this->repository->method('findByTenant')->willReturn([]);

        $command = new ImportPriceListCommand($this->tenantId, [
            ['name' => 'Test', 'priority' => 9999], // priority too high
        ]);

        $result = ($this->handler)($command);

        self::assertSame(1, $result->errorCount);
    }

    // -----------------------------------------------------------------------
    // Mixed success/error
    // -----------------------------------------------------------------------

    #[Test]
    public function itProcessesPartialImportWithSomeErrors(): void
    {
        $this->repository->method('findByTenant')->willReturn([]);
        $this->repository->expects(self::once())->method('save');

        $command = new ImportPriceListCommand($this->tenantId, [
            $this->makeValidRow('Good List'),
            ['priority' => 100], // invalid – missing name
        ]);

        $result = ($this->handler)($command);

        self::assertSame(1, $result->createdCount);
        self::assertSame(1, $result->errorCount);
        self::assertTrue($result->hasPartialSuccess());
    }

    #[Test]
    public function itCreatesActivePriceListWhenIsActiveFlagIsTrue(): void
    {
        $this->repository->method('findByTenant')->willReturn([]);

        $savedPriceLists = [];
        $this->repository->method('save')->willReturnCallback(
            function (PriceList $pl) use (&$savedPriceLists) { $savedPriceLists[] = $pl; }
        );

        $row = $this->makeValidRow('Active List');
        $row['is_active'] = '1';

        $command = new ImportPriceListCommand($this->tenantId, [$row]);
        $result = ($this->handler)($command);

        self::assertSame(1, $result->createdCount);
        self::assertTrue($savedPriceLists[0]->isActive());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function makeValidRow(string $name): array
    {
        return [
            'name' => $name,
            'priority' => '100',
            'valid_from' => null,
            'valid_to' => null,
            'is_active' => '0',
            'scope' => null,
            'discount_type' => null,
            'discount_value' => null,
        ];
    }

    private function makePriceListMock(string $name): PriceList
    {
        $nameMock = $this->createMock(\App\Pricing\Domain\Model\PriceListName::class);
        $nameMock->method('value')->willReturn($name);

        $priceList = $this->createMock(PriceList::class);
        $priceList->method('name')->willReturn($nameMock);
        $priceList->method('rules')->willReturn([]);
        $priceList->method('isActive')->willReturn(false);

        return $priceList;
    }
}
