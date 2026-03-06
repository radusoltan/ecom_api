<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Infrastructure\Persistence\Doctrine\Type\ProductIdType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductIdType::class)]
final class CatalogTypesTest extends TestCase
{
    private ProductIdType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new ProductIdType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    // ---------------------------------------------------------------
    // ProductIdType
    // ---------------------------------------------------------------

    #[Test]
    public function itConvertsStringToProductIdOnRead(): void
    {
        $ulid = '550e8400-e29b-41d4-a716-446655440000';

        $result = $this->type->convertToPHPValue($ulid, $this->platform);

        self::assertInstanceOf(ProductId::class, $result);
        self::assertSame($ulid, $result->toString());
    }

    #[Test]
    public function itConvertsProductIdToStringOnWrite(): void
    {
        $ulid = '550e8400-e29b-41d4-a716-446655440000';
        $productId = ProductId::fromString($ulid);

        $result = $this->type->convertToDatabaseValue($productId, $this->platform);

        self::assertSame($ulid, $result);
    }

    #[Test]
    public function itReturnsNullWhenPhpValueIsNullOnRead(): void
    {
        $result = $this->type->convertToPHPValue(null, $this->platform);

        self::assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenEmptyStringOnRead(): void
    {
        $result = $this->type->convertToPHPValue('', $this->platform);

        self::assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenDatabaseValueIsNullOnWrite(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        self::assertNull($result);
    }

    #[Test]
    public function itThrowsWhenInvalidTypePassedOnWrite(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->type->convertToDatabaseValue('not-a-product-id-object', $this->platform);
    }

    #[Test]
    public function itReturnsSqlDeclaration(): void
    {
        $result = $this->type->getSQLDeclaration([], $this->platform);

        self::assertSame('UUID', $result);
    }
}
