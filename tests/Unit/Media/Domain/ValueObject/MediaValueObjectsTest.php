<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\CropArea;
use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\MimeType;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Domain\ValueObject\OwnerType;
use App\Media\Domain\ValueObject\SizeLabel;
use App\Media\Domain\ValueObject\ThumbnailId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropArea::class)]
#[CoversClass(FilePath::class)]
#[CoversClass(ImageId::class)]
#[CoversClass(MimeType::class)]
#[CoversClass(OwnerReference::class)]
#[CoversClass(OwnerType::class)]
#[CoversClass(SizeLabel::class)]
#[CoversClass(ThumbnailId::class)]
final class MediaValueObjectsTest extends TestCase
{
    // --- ImageId ---

    #[Test]
    public function imageIdGenerate(): void
    {
        $id = ImageId::generate();
        self::assertNotEmpty($id->toString());
        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function imageIdFromString(): void
    {
        $id = ImageId::generate();
        $reconstructed = ImageId::fromString($id->toString());
        self::assertTrue($id->equals($reconstructed));
    }

    #[Test]
    public function imageIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ImageId::fromString('not-a-uuid');
    }

    #[Test]
    public function imageIdEquality(): void
    {
        $id = ImageId::generate();
        $other = ImageId::generate();
        self::assertTrue($id->equals($id));
        self::assertFalse($id->equals($other));
    }

    // --- ThumbnailId ---

    #[Test]
    public function thumbnailIdGenerate(): void
    {
        $id = ThumbnailId::generate();
        self::assertNotEmpty($id->toString());
        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function thumbnailIdFromString(): void
    {
        $id = ThumbnailId::generate();
        $reconstructed = ThumbnailId::fromString($id->toString());
        self::assertTrue($id->equals($reconstructed));
    }

    #[Test]
    public function thumbnailIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ThumbnailId::fromString('invalid');
    }

    // --- FilePath ---

    #[Test]
    public function filePathFromString(): void
    {
        $path = FilePath::fromString('/uploads/image.jpg');
        self::assertSame('/uploads/image.jpg', $path->toString());
        self::assertSame('/uploads/image.jpg', (string) $path);
    }

    #[Test]
    public function filePathTrimsWhitespace(): void
    {
        $path = FilePath::fromString('  /uploads/image.jpg  ');
        self::assertSame('/uploads/image.jpg', $path->toString());
    }

    #[Test]
    public function filePathRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        FilePath::fromString('');
    }

    #[Test]
    public function filePathRejectsRelative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be absolute');
        FilePath::fromString('relative/path.jpg');
    }

    #[Test]
    public function filePathEquality(): void
    {
        $a = FilePath::fromString('/uploads/a.jpg');
        $b = FilePath::fromString('/uploads/a.jpg');
        $c = FilePath::fromString('/uploads/b.jpg');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    // --- MimeType ---

    #[Test]
    public function mimeTypeAcceptsAllowed(): void
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'];
        foreach ($allowed as $type) {
            $mt = MimeType::fromString($type);
            self::assertSame($type, $mt->toString());
        }
    }

    #[Test]
    public function mimeTypeRejectsUnsupported(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported');
        MimeType::fromString('image/bmp');
    }

    #[Test]
    public function mimeTypeRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MimeType::fromString('');
    }

    #[Test]
    public function mimeTypeEquality(): void
    {
        $a = MimeType::fromString('image/jpeg');
        $b = MimeType::fromString('image/jpeg');
        $c = MimeType::fromString('image/png');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function mimeTypeStringable(): void
    {
        $mt = MimeType::fromString('image/webp');
        self::assertSame('image/webp', (string) $mt);
    }

    // --- CropArea ---

    #[Test]
    public function cropAreaFromDimensions(): void
    {
        $crop = CropArea::fromDimensions(10, 20, 100, 200);

        self::assertSame(10, $crop->x());
        self::assertSame(20, $crop->y());
        self::assertSame(100, $crop->width());
        self::assertSame(200, $crop->height());
    }

    #[Test]
    public function cropAreaToArray(): void
    {
        $crop = CropArea::fromDimensions(5, 10, 50, 60);
        $expected = ['x' => 5, 'y' => 10, 'width' => 50, 'height' => 60];

        self::assertSame($expected, $crop->toArray());
    }

    #[Test]
    public function cropAreaRejectsZeroWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CropArea::fromDimensions(0, 0, 0, 100);
    }

    #[Test]
    public function cropAreaRejectsZeroHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CropArea::fromDimensions(0, 0, 100, 0);
    }

    #[Test]
    public function cropAreaRejectsNegativeCoordinates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('negative');
        CropArea::fromDimensions(-1, 0, 100, 100);
    }

    #[Test]
    public function cropAreaAcceptsZeroCoordinates(): void
    {
        $crop = CropArea::fromDimensions(0, 0, 100, 100);
        self::assertSame(0, $crop->x());
        self::assertSame(0, $crop->y());
    }

    // --- OwnerType ---

    #[Test]
    public function ownerTypeValues(): void
    {
        self::assertSame('product', OwnerType::PRODUCT->value);
        self::assertSame('category', OwnerType::CATEGORY->value);
        self::assertSame('user', OwnerType::USER->value);
    }

    #[Test]
    public function ownerTypeFromString(): void
    {
        self::assertSame(OwnerType::PRODUCT, OwnerType::fromString('product'));
        self::assertSame(OwnerType::CATEGORY, OwnerType::fromString('category'));
        self::assertSame(OwnerType::USER, OwnerType::fromString('user'));
    }

    #[Test]
    public function ownerTypeRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OwnerType::fromString('unknown');
    }

    // --- SizeLabel ---

    #[Test]
    public function sizeLabelValues(): void
    {
        self::assertSame('sm', SizeLabel::SMALL->value);
        self::assertSame('md', SizeLabel::MEDIUM->value);
        self::assertSame('lg', SizeLabel::LARGE->value);
        self::assertSame('xl', SizeLabel::EXTRA_LARGE->value);
    }

    #[Test]
    public function sizeLabelFromString(): void
    {
        self::assertSame(SizeLabel::SMALL, SizeLabel::fromString('sm'));
        self::assertSame(SizeLabel::MEDIUM, SizeLabel::fromString('md'));
        self::assertSame(SizeLabel::LARGE, SizeLabel::fromString('lg'));
        self::assertSame(SizeLabel::EXTRA_LARGE, SizeLabel::fromString('xl'));
    }

    #[Test]
    public function sizeLabelRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SizeLabel::fromString('xxl');
    }

    // --- OwnerReference ---

    #[Test]
    public function ownerReferenceCreation(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $ref = OwnerReference::from($tenantId, OwnerType::PRODUCT, 'prod-123');

        self::assertSame($tenantId, $ref->tenantId());
        self::assertSame(OwnerType::PRODUCT, $ref->type());
        self::assertSame('prod-123', $ref->ownerId());
    }

    #[Test]
    public function ownerReferenceRejectsEmptyOwnerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OwnerReference::from(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            OwnerType::PRODUCT,
            ''
        );
    }

    #[Test]
    public function ownerReferenceRejectsWhitespaceOwnerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OwnerReference::from(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            OwnerType::CATEGORY,
            '   '
        );
    }
}
