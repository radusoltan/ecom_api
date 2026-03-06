<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command\BulkImportProducts;

use App\Catalog\Application\Command\BulkImportProducts\BulkImportProductsCommand;
use App\Catalog\Application\Command\BulkImportProducts\BulkImportProductsHandler;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('catalog')]
final class BulkImportProductsHandlerTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private BulkImportProductsHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new BulkImportProductsHandler($this->productRepository);
    }

    public function testFailsWhenNameMissing(): void
    {
        $command = new BulkImportProductsCommand([
            [
                'priceAmount' => 1999,
                'tenantId' => '00000000-0000-4000-8000-000000000001',
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['failed']);
        self::assertSame('Product name is required', $result['errors'][0]['message']);
    }

    public function testFailsWhenPriceInvalid(): void
    {
        $command = new BulkImportProductsCommand([
            [
                'name' => 'Widget',
                'priceAmount' => 0,
                'tenantId' => '00000000-0000-4000-8000-000000000001',
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['failed']);
        self::assertSame('Valid price amount is required', $result['errors'][0]['message']);
    }

    public function testMixedValidationFailures(): void
    {
        $command = new BulkImportProductsCommand([
            [
                'name' => '',
                'priceAmount' => 999,
            ],
            [
                'name' => 'Valid',
                'priceAmount' => -10,
            ],
            [
                'priceAmount' => 500,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertSame(0, $result['imported']);
        self::assertSame(3, $result['failed']);
        self::assertCount(3, $result['errors']);
    }

    public function testEmptyProductsArray(): void
    {
        $command = new BulkImportProductsCommand([]);

        $result = ($this->handler)($command);

        self::assertSame(0, $result['imported']);
        self::assertSame(0, $result['failed']);
        self::assertSame([], $result['errors']);
    }
}
