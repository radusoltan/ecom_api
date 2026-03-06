<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\ValueObject;

use App\User\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Username::class)]
final class UsernameTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Happy path construction
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesUsernameFromValidString(): void
    {
        $username = Username::fromString('john_doe');

        self::assertSame('john_doe', $username->toString());
    }

    #[Test]
    public function itAcceptsMinimumLength(): void
    {
        $username = Username::fromString('abc');

        self::assertSame('abc', $username->toString());
    }

    #[Test]
    public function itAcceptsMaximumLength(): void
    {
        $longName = str_repeat('a', 50);
        $username = Username::fromString($longName);

        self::assertSame($longName, $username->toString());
    }

    #[Test]
    public function itAcceptsAlphanumericAndHyphen(): void
    {
        $username = Username::fromString('user-123');

        self::assertSame('user-123', $username->toString());
    }

    #[Test]
    public function itImplementsStringable(): void
    {
        $username = Username::fromString('testuser');

        self::assertSame('testuser', (string) $username);
    }

    // -----------------------------------------------------------------------
    // Equality
    // -----------------------------------------------------------------------

    #[Test]
    public function itEqualsSameValue(): void
    {
        $a = Username::fromString('alice');
        $b = Username::fromString('alice');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentValue(): void
    {
        $a = Username::fromString('alice');
        $b = Username::fromString('bob99');

        self::assertFalse($a->equals($b));
    }

    // -----------------------------------------------------------------------
    // Validation failures
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');

        Username::fromString('');
    }

    #[Test]
    public function itThrowsWhenTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 3');

        Username::fromString('ab');
    }

    #[Test]
    public function itThrowsWhenTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceed 50');

        Username::fromString(str_repeat('x', 51));
    }

    #[Test]
    public function itThrowsWhenContainsSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Username::fromString('john doe');
    }

    #[Test]
    public function itThrowsWhenContainsSpecialChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Username::fromString('user@name');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validUsernamesProvider(): array
    {
        return [
            'simple alpha' => ['johndoe'],
            'with underscore' => ['john_doe'],
            'with hyphen' => ['john-doe'],
            'numbers only' => ['123456'],
            'mixed' => ['John_Doe-42'],
        ];
    }

    #[Test]
    #[DataProvider('validUsernamesProvider')]
    public function itAcceptsValidUsernames(string $value): void
    {
        $username = Username::fromString($value);

        self::assertSame($value, $username->toString());
    }
}
