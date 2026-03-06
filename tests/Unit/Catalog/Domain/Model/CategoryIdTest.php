<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\CategoryId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryId::class)]
final class CategoryIdTest extends TestCase
{
    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = CategoryId::generate();
        $id2 = CategoryId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itGeneratesValidUuid(): void
    {
        $id = CategoryId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->toString()
        );
    }

    #[Test]
    public function itCreatesFromValidUuidString(): void
    {
        $uuid = '9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab';
        $id = CategoryId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    #[Test]
    public function itThrowsOnInvalidUuidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CategoryId');

        CategoryId::fromString('not-a-uuid');
    }

    #[Test]
    public function itEqualsSameUuid(): void
    {
        $uuid = '9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab';
        $id1 = CategoryId::fromString($uuid);
        $id2 = CategoryId::fromString($uuid);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentUuid(): void
    {
        $id1 = CategoryId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $id2 = CategoryId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-abcdefabcdef');

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itCastsToString(): void
    {
        $uuid = '9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab';
        $id = CategoryId::fromString($uuid);

        self::assertSame($uuid, (string) $id);
    }
}
