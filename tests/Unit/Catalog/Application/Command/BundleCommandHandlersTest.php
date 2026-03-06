<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\CreateBundleCommand;
use App\Catalog\Application\Command\CreateBundleCommandHandler;
use App\Catalog\Application\Command\RemoveBundleCommand;
use App\Catalog\Application\Command\RemoveBundleCommandHandler;
use App\Catalog\Application\Command\UpdateBundleCommand;
use App\Catalog\Application\Command\UpdateBundleCommandHandler;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\ValueObject\ProductType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateBundleCommandHandler::class)]
#[CoversClass(UpdateBundleCommandHandler::class)]
#[CoversClass(RemoveBundleCommandHandler::class)]
final class BundleCommandHandlersTest extends TestCase
{
    private ProductId $bundleProductId;
    private ProductId $itemProductId;

    /** @var array<array{productId: string, quantity: int, price: array{amount: int, currency: string}}> */
    private array $validItems;

    protected function setUp(): void
    {
        $this->bundleProductId = ProductId::generate();
        $this->itemProductId = ProductId::generate();
        $this->validItems = [
            [
                'productId' => $this->itemProductId->toString(),
                'quantity' => 2,
                'price' => ['amount' => 1000, 'currency' => 'USD'],
            ],
        ];
    }

    // ---------------------------------------------------------------
    // CreateBundleCommandHandler
    // ---------------------------------------------------------------

    #[Test]
    public function createBundleThrowsWhenBundleProductNotFound(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new CreateBundleCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product with ID');

        $handler(new CreateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 0.0,
        ));
    }

    #[Test]
    public function createBundleThrowsWhenItemProductNotFound(): void
    {
        $bundleProduct = $this->createMock(Product::class);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnCallback(function (ProductId $id) use ($bundleProduct): ?Product {
                if ($id->equals($this->bundleProductId)) {
                    return $bundleProduct;
                }

                return null;
            });

        $handler = new CreateBundleCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Bundle item product with ID');

        $handler(new CreateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 0.0,
        ));
    }

    #[Test]
    public function createBundleThrowsWhenItemIsBundleProduct(): void
    {
        $bundleProduct = $this->createMock(Product::class);

        $itemType = $this->createMock(ProductType::class);
        $itemType->method('isBundle')->willReturn(true);

        $itemProduct = $this->createMock(Product::class);
        $itemProduct->method('type')->willReturn($itemType);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnCallback(function (ProductId $id) use ($bundleProduct, $itemProduct): ?Product {
                if ($id->equals($this->bundleProductId)) {
                    return $bundleProduct;
                }

                return $itemProduct;
            });

        $handler = new CreateBundleCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Circular references are not allowed');

        $handler(new CreateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 0.0,
        ));
    }

    #[Test]
    public function createBundleSavesProductOnSuccess(): void
    {
        $bundleProduct = $this->createMock(Product::class);
        $bundleProduct->expects(self::once())->method('createBundle');

        $itemType = $this->createMock(ProductType::class);
        $itemType->method('isBundle')->willReturn(false);

        $itemProduct = $this->createMock(Product::class);
        $itemProduct->method('type')->willReturn($itemType);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnCallback(function (ProductId $id) use ($bundleProduct, $itemProduct): ?Product {
                if ($id->equals($this->bundleProductId)) {
                    return $bundleProduct;
                }

                return $itemProduct;
            });
        $repo->expects(self::once())->method('save')->with($bundleProduct);

        $handler = new CreateBundleCommandHandler($repo);
        $handler(new CreateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 5.0,
        ));
    }

    // ---------------------------------------------------------------
    // UpdateBundleCommandHandler
    // ---------------------------------------------------------------

    #[Test]
    public function updateBundleThrowsWhenBundleProductNotFound(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new UpdateBundleCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product with ID');

        $handler(new UpdateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 0.0,
        ));
    }

    #[Test]
    public function updateBundleThrowsWhenItemProductNotFound(): void
    {
        $bundleProduct = $this->createMock(Product::class);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnCallback(function (ProductId $id) use ($bundleProduct): ?Product {
                if ($id->equals($this->bundleProductId)) {
                    return $bundleProduct;
                }

                return null;
            });

        $handler = new UpdateBundleCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Bundle item product with ID');

        $handler(new UpdateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 0.0,
        ));
    }

    #[Test]
    public function updateBundleThrowsWhenItemIsBundleProduct(): void
    {
        $bundleProduct = $this->createMock(Product::class);

        $itemType = $this->createMock(ProductType::class);
        $itemType->method('isBundle')->willReturn(true);

        $itemProduct = $this->createMock(Product::class);
        $itemProduct->method('type')->willReturn($itemType);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnCallback(function (ProductId $id) use ($bundleProduct, $itemProduct): ?Product {
                if ($id->equals($this->bundleProductId)) {
                    return $bundleProduct;
                }

                return $itemProduct;
            });

        $handler = new UpdateBundleCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Circular references are not allowed');

        $handler(new UpdateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 0.0,
        ));
    }

    #[Test]
    public function updateBundleSavesProductOnSuccess(): void
    {
        $bundleProduct = $this->createMock(Product::class);
        $bundleProduct->expects(self::once())->method('updateBundle');

        $itemType = $this->createMock(ProductType::class);
        $itemType->method('isBundle')->willReturn(false);

        $itemProduct = $this->createMock(Product::class);
        $itemProduct->method('type')->willReturn($itemType);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnCallback(function (ProductId $id) use ($bundleProduct, $itemProduct): ?Product {
                if ($id->equals($this->bundleProductId)) {
                    return $bundleProduct;
                }

                return $itemProduct;
            });
        $repo->expects(self::once())->method('save')->with($bundleProduct);

        $handler = new UpdateBundleCommandHandler($repo);
        $handler(new UpdateBundleCommand(
            bundleProductId: $this->bundleProductId,
            items: $this->validItems,
            discountPercentage: 10.0,
        ));
    }

    // ---------------------------------------------------------------
    // RemoveBundleCommandHandler
    // ---------------------------------------------------------------

    #[Test]
    public function removeBundleThrowsWhenProductNotFound(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new RemoveBundleCommandHandler($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product with ID');

        $handler(new RemoveBundleCommand(bundleProductId: $this->bundleProductId));
    }

    #[Test]
    public function removeBundleCallsRemoveBundleAndSaves(): void
    {
        $bundleProduct = $this->createMock(Product::class);
        $bundleProduct->expects(self::once())->method('removeBundle');

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($bundleProduct);
        $repo->expects(self::once())->method('save')->with($bundleProduct);

        $handler = new RemoveBundleCommandHandler($repo);
        $handler(new RemoveBundleCommand(bundleProductId: $this->bundleProductId));
    }
}
