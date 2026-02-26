<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\MimeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MimeType::class)]
final class MimeTypeTest extends TestCase
{
    // -------------------------------------------------------------------
    // Allowed types
    // -------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function allowedMimeTypes(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'avif' => ['image/avif'],
            'gif' => ['image/gif'],
        ];
    }

    #[Test]
    #[DataProvider('allowedMimeTypes')]
    public function itCreatesFromAllowedType(string $mimeType): void
    {
        $vo = MimeType::fromString($mimeType);

        self::assertSame($mimeType, $vo->toString());
    }

    #[Test]
    public function itTrimsWhitespaceBeforeValidation(): void
    {
        $vo = MimeType::fromString('  image/jpeg  ');

        self::assertSame('image/jpeg', $vo->toString());
    }

    // -------------------------------------------------------------------
    // Rejection
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenValueIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mime type cannot be empty.');

        MimeType::fromString('');
    }

    #[Test]
    public function itThrowsWhenTypeIsNotAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported image mime type "application/pdf".');

        MimeType::fromString('application/pdf');
    }

    #[Test]
    public function itThrowsForSvg(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MimeType::fromString('image/svg+xml');
    }

    // -------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------

    #[Test]
    public function itEqualsSameType(): void
    {
        $a = MimeType::fromString('image/jpeg');
        $b = MimeType::fromString('image/jpeg');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentType(): void
    {
        $a = MimeType::fromString('image/jpeg');
        $b = MimeType::fromString('image/png');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itCastsToString(): void
    {
        $vo = MimeType::fromString('image/webp');

        self::assertSame('image/webp', (string) $vo);
    }
}
