<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\FilePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilePath::class)]
final class FilePathTest extends TestCase
{
    // -------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesFromAbsolutePath(): void
    {
        $path = FilePath::fromString('/images/product/photo.jpg');

        self::assertSame('/images/product/photo.jpg', $path->toString());
    }

    #[Test]
    public function itTrimsWhitespaceBeforeValidation(): void
    {
        $path = FilePath::fromString('  /images/photo.jpg  ');

        self::assertSame('/images/photo.jpg', $path->toString());
    }

    #[Test]
    public function itThrowsWhenValueIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File path cannot be empty.');

        FilePath::fromString('');
    }

    #[Test]
    public function itThrowsWhenValueIsOnlyWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File path cannot be empty.');

        FilePath::fromString('   ');
    }

    #[Test]
    public function itThrowsWhenPathIsRelative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File path must be absolute.');

        FilePath::fromString('images/photo.jpg');
    }

    // -------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------

    #[Test]
    public function itEqualsSameValue(): void
    {
        $a = FilePath::fromString('/images/photo.jpg');
        $b = FilePath::fromString('/images/photo.jpg');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentValue(): void
    {
        $a = FilePath::fromString('/images/photo.jpg');
        $b = FilePath::fromString('/images/other.jpg');

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------------------
    // String casting
    // -------------------------------------------------------------------

    #[Test]
    public function itCastsToString(): void
    {
        $path = FilePath::fromString('/images/test.png');

        self::assertSame('/images/test.png', (string) $path);
    }
}
