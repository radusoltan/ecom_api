<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\ValueObject;

use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserId::class)]
final class UserIdTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Construction
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidUuidV4(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = UserId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    #[Test]
    public function itGeneratesValidUuid(): void
    {
        $id = UserId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $a = UserId::generate();
        $b = UserId::generate();

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itImplementsStringable(): void
    {
        $id = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $id);
    }

    // -----------------------------------------------------------------------
    // Equality
    // -----------------------------------------------------------------------

    #[Test]
    public function itEqualsSameUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $a = UserId::fromString($uuid);
        $b = UserId::fromString($uuid);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentUuid(): void
    {
        $a = UserId::generate();
        $b = UserId::generate();

        self::assertFalse($a->equals($b));
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsForInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('not-a-valid-uuid');
    }

    #[Test]
    public function itThrowsForEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('');
    }
}
