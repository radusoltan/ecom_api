<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Service;

use App\Catalog\Application\Service\SkuGeneratorService;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SkuGeneratorService::class)]
final class SkuGeneratorServiceTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private Connection $connection;
    private CategoryRepositoryInterface $categoryRepository;
    private TenantRepositoryInterface $tenantRepository;
    private SkuGeneratorService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->tenantRepository = $this->createMock(TenantRepositoryInterface::class);

        $this->service = new SkuGeneratorService(
            $this->connection,
            $this->categoryRepository,
            $this->tenantRepository,
        );

        $this->tenantId = TenantId::fromString(self::TENANT_ID);
    }

    // -----------------------------------------------------------------------
    // generate() — no category (uses 'PRD' prefix)
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesSkuWithPrdPrefixWhenNoCategoryProvided(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('1');

        $sku = $this->service->generate($this->tenantId);

        self::assertInstanceOf(SKU::class, $sku);
        self::assertSame('PRD-000001', $sku->value());
    }

    #[Test]
    public function itGeneratesSkuWithCorrectSequenceNumber(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('42');

        $sku = $this->service->generate($this->tenantId);

        self::assertSame('PRD-000042', $sku->value());
    }

    #[Test]
    public function itGeneratesSkuWithMaxSixDigitSequence(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('999999');

        $sku = $this->service->generate($this->tenantId);

        self::assertSame('PRD-999999', $sku->value());
    }

    #[Test]
    public function itThrowsRuntimeExceptionWhenSequenceQueryFails(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate SKU sequence.');

        $this->service->generate($this->tenantId);
    }

    // -----------------------------------------------------------------------
    // generate() — with category (uses derived prefix)
    // -----------------------------------------------------------------------

    #[Test]
    public function itUsesCategoryNamePrefixWhenCategoryExists(): void
    {
        $categoryId = CategoryId::generate();
        $category = $this->buildCategory($categoryId, $this->tenantId, 'Electronics');

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('7');

        $sku = $this->service->generate($this->tenantId, $categoryId);

        // "Electronics" -> upper -> filtered -> "ELE" (first 3 chars)
        self::assertSame('ELE-000007', $sku->value());
    }

    #[Test]
    public function itFallsBackToPrdWhenCategoryNotFound(): void
    {
        $categoryId = CategoryId::generate();

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn(null);

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('5');

        $sku = $this->service->generate($this->tenantId, $categoryId);

        self::assertSame('PRD-000005', $sku->value());
    }

    #[Test]
    public function itFallsBackToPrdWhenCategoryBelongsToDifferentTenant(): void
    {
        $categoryId = CategoryId::generate();
        $otherTenantId = TenantId::generate();
        $category = $this->buildCategory($categoryId, $otherTenantId, 'Sports');

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($category);

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('3');

        $sku = $this->service->generate($this->tenantId, $categoryId);

        self::assertSame('PRD-000003', $sku->value());
    }

    // -----------------------------------------------------------------------
    // prefix derivation edge cases (buildCode logic)
    // -----------------------------------------------------------------------

    #[Test]
    public function itPadsCodeWithXWhenCategoryNameIsTooShort(): void
    {
        $categoryId = CategoryId::generate();
        // Single letter name — CategoryName requires min 2 chars, use "Ab"
        // "Ab" -> "AB" -> 2 chars -> padded to "ABX"
        $category = $this->buildCategory($categoryId, $this->tenantId, 'Ab');

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($category);

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('1');

        $sku = $this->service->generate($this->tenantId, $categoryId);

        self::assertSame('ABX-000001', $sku->value());
    }

    #[Test]
    public function itTruncatesPrefixToThreeCharacters(): void
    {
        $categoryId = CategoryId::generate();
        // "Clothing" -> "CLOTHING" -> first 3 chars -> "CLO"
        $category = $this->buildCategory($categoryId, $this->tenantId, 'Clothing');

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($category);

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('10');

        $sku = $this->service->generate($this->tenantId, $categoryId);

        self::assertSame('CLO-000010', $sku->value());
    }

    #[Test]
    public function itStripsNonAlphanumericCharactersFromCategoryName(): void
    {
        $categoryId = CategoryId::generate();
        // "Hi-Fi" -> stripped "HIFI" -> first 3 chars -> "HIF"
        $category = $this->buildCategory($categoryId, $this->tenantId, 'Hi-Fi');

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($category);

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('2');

        $sku = $this->service->generate($this->tenantId, $categoryId);

        self::assertSame('HIF-000002', $sku->value());
    }

    #[Test]
    public function itPadsCodeWithXWhenFilteredNameIsTwoLetters(): void
    {
        $categoryId = CategoryId::generate();
        // "Go" -> "GO" (2 alpha chars) -> padded to "GOX"
        $category = $this->buildCategory($categoryId, $this->tenantId, 'Go');

        $this->categoryRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($category);

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('1');

        $sku = $this->service->generate($this->tenantId, $categoryId);

        self::assertSame('GOX-000001', $sku->value());
    }

    #[Test]
    public function itReturnsSkuInstanceFromGenerate(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn('100');

        $result = $this->service->generate($this->tenantId);

        self::assertInstanceOf(SKU::class, $result);
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function buildCategory(CategoryId $id, TenantId $tenantId, string $name): Category
    {
        return Category::create(
            id: $id,
            tenantId: $tenantId,
            name: CategoryName::fromString($name),
            description: null,
            parentId: null,
        );
    }
}
