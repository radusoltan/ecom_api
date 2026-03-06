<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionId::class)]
final class SessionIdTest extends TestCase
{
    #[Test]
    public function itGeneratesValidSessionId(): void
    {
        $id = SessionId::generate();

        self::assertInstanceOf(SessionId::class, $id);
        self::assertNotEmpty($id->toString());
    }

    #[Test]
    public function itCreatesFromValidUuid(): void
    {
        $generated = SessionId::generate();
        $id = SessionId::fromString($generated->toString());

        self::assertSame($generated->toString(), $id->toString());
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SessionId cannot be empty');

        SessionId::fromString('');
    }

    #[Test]
    public function itThrowsForInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SessionId format');

        SessionId::fromString('not-a-valid-uuid');
    }

    #[Test]
    public function itEqualsAnotherIdWithSameValue(): void
    {
        $value = SessionId::generate()->toString();
        $id1 = SessionId::fromString($value);
        $id2 = SessionId::fromString($value);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentId(): void
    {
        $id1 = SessionId::generate();
        $id2 = SessionId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itImplementsStringableMagicMethod(): void
    {
        $id = SessionId::generate();

        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function itThrowsForZeroString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SessionId cannot be empty');

        SessionId::fromString('0');
    }
}
