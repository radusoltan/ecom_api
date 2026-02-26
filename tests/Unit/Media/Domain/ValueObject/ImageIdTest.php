<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\ImageId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageId::class)]
final class ImageIdTest extends TestCase
{
    // -------------------------------------------------------------------
    // Generation
    // -------------------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = ImageId::generate();
        $id2 = ImageId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itGeneratesValidUuidString(): void
    {
        $id = ImageId::generate();

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
        $uuid = '01920000-0000-7000-8000-000000000001';
        $id = ImageId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    #[Test]
    public function itThrowsWhenValueIsNotAUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ImageId.');

        ImageId::fromString('not-a-uuid');
    }

    #[Test]
    public function itThrowsWhenValueIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ImageId::fromString('');
    }

    // -------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------

    #[Test]
    public function itEqualsSameId(): void
    {
        $uuid = '01920000-0000-7000-8000-000000000002';
        $a = ImageId::fromString($uuid);
        $b = ImageId::fromString($uuid);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentId(): void
    {
        $a = ImageId::fromString('01920000-0000-7000-8000-000000000001');
        $b = ImageId::fromString('01920000-0000-7000-8000-000000000002');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itCastsToString(): void
    {
        $uuid = '01920000-0000-7000-8000-000000000001';
        $id = ImageId::fromString($uuid);

        self::assertSame($uuid, (string) $id);
    }
}
