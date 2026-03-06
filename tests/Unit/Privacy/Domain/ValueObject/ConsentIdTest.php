<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\ValueObject;

use App\Privacy\Domain\ValueObject\ConsentId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentId::class)]
final class ConsentIdTest extends TestCase
{
    private const VALID_ULID = '01HXYZ1234567890ABCDE12345';

    // -------
    // generate()
    // -------

    #[Test]
    public function generateCreatesValidInstance(): void
    {
        $id = ConsentId::generate();

        self::assertInstanceOf(ConsentId::class, $id);
    }

    #[Test]
    public function generateCreatesUniqueIds(): void
    {
        $id1 = ConsentId::generate();
        $id2 = ConsentId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function generateProducesNonEmptyString(): void
    {
        $id = ConsentId::generate();

        self::assertNotEmpty($id->toString());
    }

    // -------
    // fromString()
    // -------

    #[Test]
    public function fromStringCreatesFromValidUlid(): void
    {
        $generated = ConsentId::generate();
        $id = ConsentId::fromString($generated->toString());

        self::assertTrue($generated->equals($id));
    }

    #[Test]
    public function fromStringThrowsWhenValueIsNotValidUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ConsentId format');

        ConsentId::fromString('not-a-valid-ulid');
    }

    #[Test]
    public function fromStringThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ConsentId format');

        ConsentId::fromString('');
    }

    #[Test]
    public function fromStringThrowsForUuidInsteadOfUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // UUID format is not valid ULID
        ConsentId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -------
    // toString() / __toString()
    // -------

    #[Test]
    public function toStringReturnsSameValuePassedIn(): void
    {
        $generated = ConsentId::generate();
        $raw = $generated->toString();
        $id = ConsentId::fromString($raw);

        self::assertSame($raw, $id->toString());
    }

    #[Test]
    public function castToStringIsEquivalentToToString(): void
    {
        $id = ConsentId::generate();

        self::assertSame($id->toString(), (string) $id);
    }

    // -------
    // equals()
    // -------

    #[Test]
    public function equalsReturnsTrueForSameValue(): void
    {
        $raw = ConsentId::generate()->toString();
        $id1 = ConsentId::fromString($raw);
        $id2 = ConsentId::fromString($raw);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentValues(): void
    {
        $id1 = ConsentId::generate();
        $id2 = ConsentId::generate();

        self::assertFalse($id1->equals($id2));
    }

    // -------
    // Stringable interface
    // -------

    #[Test]
    public function itImplementsStringableInterface(): void
    {
        $id = ConsentId::generate();

        self::assertInstanceOf(\Stringable::class, $id);
    }
}
