<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\ValueObject;

use App\User\Domain\ValueObject\HashedPassword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HashedPassword::class)]
final class HashedPasswordTest extends TestCase
{
    // -----------------------------------------------------------------------
    // fromHash
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidHash(): void
    {
        $hash = '$2y$10$abcdefghij123456789012uVfEDMbqS1l1JBKxfzJT5d01Qo6MGZXW';
        $password = HashedPassword::fromHash($hash);

        self::assertSame($hash, $password->toString());
    }

    #[Test]
    public function itThrowsForEmptyHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');

        HashedPassword::fromHash('');
    }

    #[Test]
    public function itThrowsForTooShortHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hashed password');

        HashedPassword::fromHash('tooshort');
    }

    // -----------------------------------------------------------------------
    // fromPlainPassword
    // -----------------------------------------------------------------------

    #[Test]
    public function itHashesPlainPassword(): void
    {
        $password = HashedPassword::fromPlainPassword('SecurePass123');

        // The result must be a non-empty hash
        self::assertNotEmpty($password->toString());
        self::assertGreaterThanOrEqual(20, strlen($password->toString()));
    }

    #[Test]
    public function itProducesDifferentHashesForSamePlainPassword(): void
    {
        $a = HashedPassword::fromPlainPassword('SamePassword1');
        $b = HashedPassword::fromPlainPassword('SamePassword1');

        // bcrypt uses random salt so hashes differ
        self::assertNotSame($a->toString(), $b->toString());
    }

    #[Test]
    public function itThrowsForEmptyPlainPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');

        HashedPassword::fromPlainPassword('');
    }

    #[Test]
    public function itThrowsWhenPlainPasswordTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 8 characters');

        HashedPassword::fromPlainPassword('short');
    }

    // -----------------------------------------------------------------------
    // Stringable
    // -----------------------------------------------------------------------

    #[Test]
    public function itImplementsStringable(): void
    {
        $hash = '$2y$10$abcdefghij123456789012uVfEDMbqS1l1JBKxfzJT5d01Qo6MGZXW';
        $password = HashedPassword::fromHash($hash);

        self::assertSame($hash, (string) $password);
    }
}
