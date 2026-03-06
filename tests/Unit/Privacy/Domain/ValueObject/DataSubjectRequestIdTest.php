<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\ValueObject;

use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataSubjectRequestId::class)]
final class DataSubjectRequestIdTest extends TestCase
{
    // -------
    // generate()
    // -------

    #[Test]
    public function generateCreatesValidInstance(): void
    {
        $id = DataSubjectRequestId::generate();

        self::assertInstanceOf(DataSubjectRequestId::class, $id);
    }

    #[Test]
    public function generateCreatesUniqueIds(): void
    {
        $id1 = DataSubjectRequestId::generate();
        $id2 = DataSubjectRequestId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function generateProducesNonEmptyString(): void
    {
        $id = DataSubjectRequestId::generate();

        self::assertNotEmpty($id->toString());
    }

    // -------
    // fromString()
    // -------

    #[Test]
    public function fromStringCreatesFromValidUlid(): void
    {
        $generated = DataSubjectRequestId::generate();
        $id = DataSubjectRequestId::fromString($generated->toString());

        self::assertTrue($generated->equals($id));
    }

    #[Test]
    public function fromStringThrowsWhenValueIsNotValidUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DataSubjectRequestId format');

        DataSubjectRequestId::fromString('not-a-valid-ulid');
    }

    #[Test]
    public function fromStringThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DataSubjectRequestId format');

        DataSubjectRequestId::fromString('');
    }

    #[Test]
    public function fromStringThrowsForUuidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // UUID format is not valid ULID
        DataSubjectRequestId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------
    // toString() / __toString()
    // -------

    #[Test]
    public function toStringReturnsSameValuePassedIn(): void
    {
        $generated = DataSubjectRequestId::generate();
        $raw = $generated->toString();
        $id = DataSubjectRequestId::fromString($raw);

        self::assertSame($raw, $id->toString());
    }

    #[Test]
    public function castToStringIsEquivalentToToString(): void
    {
        $id = DataSubjectRequestId::generate();

        self::assertSame($id->toString(), (string) $id);
    }

    // -------
    // equals()
    // -------

    #[Test]
    public function equalsReturnsTrueForSameValue(): void
    {
        $raw = DataSubjectRequestId::generate()->toString();
        $id1 = DataSubjectRequestId::fromString($raw);
        $id2 = DataSubjectRequestId::fromString($raw);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentValues(): void
    {
        $id1 = DataSubjectRequestId::generate();
        $id2 = DataSubjectRequestId::generate();

        self::assertFalse($id1->equals($id2));
    }

    // -------
    // Stringable interface
    // -------

    #[Test]
    public function itImplementsStringableInterface(): void
    {
        $id = DataSubjectRequestId::generate();

        self::assertInstanceOf(\Stringable::class, $id);
    }
}
