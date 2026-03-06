<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Pagination;

use App\Shared\Application\Pagination\Cursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursor::class)]
final class CursorTest extends TestCase
{
    // -----------------------------------------------------------------------
    // encode
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncodesToValidBase64(): void
    {
        $encoded = Cursor::encode('abc-123', 'value', 'name');

        self::assertNotEmpty($encoded);
        self::assertNotFalse(base64_decode($encoded, strict: true));
    }

    #[Test]
    public function itEncodeDefaultSortFieldIsId(): void
    {
        $encoded = Cursor::encode('abc-123', null);
        $cursor = Cursor::decode($encoded);

        self::assertSame('id', $cursor->sortField);
    }

    #[Test]
    public function itEncodesAndPreservesAllFields(): void
    {
        $encoded = Cursor::encode('my-id', 42, 'price');
        $cursor = Cursor::decode($encoded);

        self::assertSame('my-id', $cursor->id);
        self::assertSame(42, $cursor->sortValue);
        self::assertSame('price', $cursor->sortField);
    }

    #[Test]
    public function itEncodesNullSortValue(): void
    {
        $encoded = Cursor::encode('uuid-1', null, 'created_at');
        $cursor = Cursor::decode($encoded);

        self::assertNull($cursor->sortValue);
        self::assertSame('uuid-1', $cursor->id);
    }

    #[Test]
    public function itEncodesSortValueAsFloat(): void
    {
        $encoded = Cursor::encode('x', 3.14, 'score');
        $cursor = Cursor::decode($encoded);

        self::assertEqualsWithDelta(3.14, $cursor->sortValue, 0.0001);
    }

    // -----------------------------------------------------------------------
    // decode
    // -----------------------------------------------------------------------

    #[Test]
    public function itDecodeConstructsObjectWithCorrectProperties(): void
    {
        $cursor = new Cursor('some-id', 'hello', 'title');

        self::assertSame('some-id', $cursor->id);
        self::assertSame('hello', $cursor->sortValue);
        self::assertSame('title', $cursor->sortField);
    }

    #[Test]
    public function itDecodeRoundtripIsStable(): void
    {
        $encoded = Cursor::encode('stable-id', 100, 'rank');
        $decoded = Cursor::decode($encoded);

        self::assertSame('stable-id', $decoded->id);
        self::assertSame(100, $decoded->sortValue);
        self::assertSame('rank', $decoded->sortField);
    }

    #[Test]
    public function itDecodeThrowsOnInvalidBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid base64');

        Cursor::decode('!!!not-base64!!!');
    }

    #[Test]
    public function itDecodeThrowsOnInvalidJson(): void
    {
        // Valid base64 of a non-JSON string
        $notJson = base64_encode('this is not json {broken');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        Cursor::decode($notJson);
    }

    #[Test]
    public function itDecodeThrowsWhenRequiredFieldsMissing(): void
    {
        // Valid base64 of JSON missing the 'id' field
        $missingId = base64_encode((string) json_encode(['sf' => 'name', 'sv' => null]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required fields');

        Cursor::decode($missingId);
    }

    #[Test]
    public function itDecodeThrowsWhenSortFieldMissing(): void
    {
        $missingSortField = base64_encode((string) json_encode(['id' => 'abc', 'sv' => null]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required fields');

        Cursor::decode($missingSortField);
    }
}
