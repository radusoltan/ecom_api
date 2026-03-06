<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductId::class)]
final class ProductIdTest extends TestCase
{
    // -------------------------------------------------------
    // Generation
    // -------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = ProductId::generate();
        $id2 = ProductId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itGeneratesValidUuid(): void
    {
        $id = ProductId::generate();

        // A UUID string has a specific length
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->toString()
        );
    }

    // -------------------------------------------------------
    // fromString
    // -------------------------------------------------------

    #[Test]
    public function itCreatesFromValidUuidString(): void
    {
        $uuid = '9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab';
        $id = ProductId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    #[Test]
    public function itThrowsOnInvalidUuidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ProductId');

        ProductId::fromString('not-a-uuid');
    }

    #[Test]
    public function itThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductId::fromString('');
    }

    // -------------------------------------------------------
    // Equality
    // -------------------------------------------------------

    #[Test]
    public function itEqualsSameUuid(): void
    {
        $uuid = '9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab';
        $id1 = ProductId::fromString($uuid);
        $id2 = ProductId::fromString($uuid);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentUuid(): void
    {
        $id1 = ProductId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $id2 = ProductId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-abcdefabcdef');

        self::assertFalse($id1->equals($id2));
    }

    // -------------------------------------------------------
    // __toString
    // -------------------------------------------------------

    #[Test]
    public function itCastsToString(): void
    {
        $uuid = '9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab';
        $id = ProductId::fromString($uuid);

        self::assertSame($uuid, (string) $id);
    }
}
