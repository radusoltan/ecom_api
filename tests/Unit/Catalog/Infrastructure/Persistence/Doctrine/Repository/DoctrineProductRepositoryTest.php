<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductRepository;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(DoctrineProductRepository::class)]
#[Group('catalog')]
final class DoctrineProductRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $eventBus;
    private DoctrineProductRepository $repository;

    private const TENANT_UUID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventBus = $this->createMock(MessageBusInterface::class);
        $this->repository = new DoctrineProductRepository(
            $this->entityManager,
            $this->eventBus,
        );
    }

    // ---------------------------------------------------------------
    // Implements correct interface
    // ---------------------------------------------------------------

    #[Test]
    public function itImplementsProductRepositoryInterface(): void
    {
        self::assertInstanceOf(ProductRepositoryInterface::class, $this->repository);
    }

    // ---------------------------------------------------------------
    // save – new entity (persist path)
    // ---------------------------------------------------------------

    #[Test]
    public function itPersistsNewProductWhenEntityDoesNotExist(): void
    {
        $product = $this->buildProduct();

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductEntity::class, $product->id()->toString())
            ->willReturn(null);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(ProductEntity::class));

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->eventBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->repository->save($product);
    }

    // ---------------------------------------------------------------
    // save – existing entity (update path)
    // ---------------------------------------------------------------

    #[Test]
    public function itUpdatesExistingProductEntityWhenAlreadyPersisted(): void
    {
        $product = $this->buildProduct();

        $existingEntity = $this->createMock(ProductEntity::class);
        $existingEntity->expects(self::once())->method('setTenantId')->with($product->tenantId()->toString());
        $existingEntity->expects(self::once())->method('setSku')->with($product->sku()->value());
        $existingEntity->expects(self::once())->method('setName')->with($product->name()->value());
        $existingEntity->expects(self::once())->method('setDescription');
        $existingEntity->expects(self::once())->method('setShortDescription');
        $existingEntity->expects(self::once())->method('setPriceAmount');
        $existingEntity->expects(self::once())->method('setPriceCurrency');
        $existingEntity->expects(self::once())->method('setCategoryId');
        $existingEntity->expects(self::once())->method('setStockQuantity');
        $existingEntity->expects(self::once())->method('setTrackInventory');
        $existingEntity->expects(self::once())->method('setAllowBackorder');
        $existingEntity->expects(self::once())->method('setImages');
        $existingEntity->expects(self::once())->method('setActive');
        $existingEntity->expects(self::once())->method('setIsFeatured');

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductEntity::class, $product->id()->toString())
            ->willReturn($existingEntity);

        $this->entityManager->expects(self::never())
            ->method('persist');

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->eventBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->repository->save($product);
    }

    // ---------------------------------------------------------------
    // findById – found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsDomainModelWhenProductFound(): void
    {
        $productId = ProductId::fromString('22222222-0000-4000-8000-000000000001');
        $product = $this->buildProduct($productId);

        $entity = $this->createMock(ProductEntity::class);
        $entity->method('toDomainModel')->willReturn($product);

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductEntity::class, $productId->toString())
            ->willReturn($entity);

        $result = $this->repository->findById($productId);

        self::assertSame($product, $result);
    }

    // ---------------------------------------------------------------
    // findById – not found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenProductNotFound(): void
    {
        $productId = ProductId::fromString('22222222-0000-4000-8000-000000000002');

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductEntity::class, $productId->toString())
            ->willReturn(null);

        $result = $this->repository->findById($productId);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // findBySKU – found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsProductFoundBySku(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_UUID);
        $sku = SKU::fromString('PRD-123456');
        $product = $this->buildProduct();

        $entity = $this->createMock(ProductEntity::class);
        $entity->method('toDomainModel')->willReturn($product);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findOneBy')
            ->with(['tenantId' => $tenantId->toString(), 'sku' => $sku->value()])
            ->willReturn($entity);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(ProductEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findBySKU($tenantId, $sku);

        self::assertSame($product, $result);
    }

    // ---------------------------------------------------------------
    // findBySKU – not found
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullWhenProductNotFoundBySku(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_UUID);
        $sku = SKU::fromString('PRD-999999');

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(ProductEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findBySKU($tenantId, $sku);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // findBySlug
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsProductFoundBySlug(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_UUID);
        $slug = Slug::fromString('test-product');
        $product = $this->buildProduct();

        $entity = $this->createMock(ProductEntity::class);
        $entity->method('toDomainModel')->willReturn($product);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findOneBy')
            ->with(['tenantId' => $tenantId->toString(), 'slug' => $slug->value()])
            ->willReturn($entity);

        $this->entityManager->method('getRepository')
            ->with(ProductEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findBySlug($tenantId, $slug);

        self::assertSame($product, $result);
    }

    // ---------------------------------------------------------------
    // findByTenant
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsMappedProductsForTenant(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_UUID);
        $product = $this->buildProduct();

        $entity = $this->createMock(ProductEntity::class);
        $entity->method('toDomainModel')->willReturn($product);

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects(self::once())
            ->method('findBy')
            ->with(
                ['tenantId' => $tenantId->toString()],
                ['createdAt' => 'DESC'],
                50,
                10
            )
            ->willReturn([$entity]);

        $this->entityManager->method('getRepository')
            ->with(ProductEntity::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByTenant($tenantId, 50, 10);

        self::assertCount(1, $result);
        self::assertSame($product, $result[0]);
    }

    // ---------------------------------------------------------------
    // delete – entity found
    // ---------------------------------------------------------------

    #[Test]
    public function itDeletesProductWhenEntityFound(): void
    {
        $productId = ProductId::fromString('33333333-0000-4000-8000-000000000001');
        $entity = $this->createMock(ProductEntity::class);

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductEntity::class, $productId->toString())
            ->willReturn($entity);

        $this->entityManager->expects(self::once())
            ->method('remove')
            ->with($entity);

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->repository->delete($productId);
    }

    // ---------------------------------------------------------------
    // delete – entity not found (no-op)
    // ---------------------------------------------------------------

    #[Test]
    public function itDoesNothingWhenDeletingNonExistentProduct(): void
    {
        $productId = ProductId::fromString('33333333-0000-4000-8000-000000000002');

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(ProductEntity::class, $productId->toString())
            ->willReturn(null);

        $this->entityManager->expects(self::never())
            ->method('remove');

        $this->entityManager->expects(self::never())
            ->method('flush');

        $this->repository->delete($productId);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildProduct(?ProductId $id = null): Product
    {
        return Product::reconstituteFromPersistence(
            id: $id ?? ProductId::fromString('22222222-0000-4000-8000-000000000001'),
            tenantId: TenantId::fromString(self::TENANT_UUID),
            sku: SKU::fromString('PRD-123456'),
            name: ProductName::fromString('Test Product'),
            description: 'Test description',
            shortDescription: null,
            slug: Slug::fromString('test-product'),
            price: Money::fromScalars(1000, 'USD'),
            categoryId: null,
            stock: Stock::create(10),
            images: [],
            status: ProductStatus::active(),
            type: ProductType::simple(),
            isFeatured: false,
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
        );
    }
}
