<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\ThumbnailId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThumbnailId::class)]
final class ThumbnailIdTest extends TestCase
{
    // -------------------------------------------------------------------
    // Generation
    // -------------------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = ThumbnailId::generate();
        $id2 = ThumbnailId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itGeneratesValidUuidString(): void
    {
        $id = ThumbnailId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->toString()
        );
    }

    // -------------------------------------------------------------------
    // Construction from string
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidUuid(): void
    {
        $uuid = '01920000-0000-7000-8000-000000000010';
        $id = ThumbnailId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    #[Test]
    public function itThrowsWhenValueIsNotAUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ThumbnailId.');

        ThumbnailId::fromString('invalid-id');
    }

    // -------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------

    #[Test]
    public function itEqualsSameId(): void
    {
        $uuid = '01920000-0000-7000-8000-000000000010';
        $a = ThumbnailId::fromString($uuid);
        $b = ThumbnailId::fromString($uuid);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentId(): void
    {
        $a = ThumbnailId::fromString('01920000-0000-7000-8000-000000000010');
        $b = ThumbnailId::fromString('01920000-0000-7000-8000-000000000011');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itCastsToString(): void
    {
        $uuid = '01920000-0000-7000-8000-000000000010';
        $id = ThumbnailId::fromString($uuid);

        self::assertSame($uuid, (string) $id);
    }
}
