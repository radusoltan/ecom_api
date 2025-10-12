<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testFromStringCreatesValidEmail(): void
    {
        $email = Email::fromString('test@example.com');

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('test@example.com', $email->value());
    }

    public function testFromStringNormalizesToLowercase(): void
    {
        $email = Email::fromString('Test@Example.COM');

        $this->assertSame('test@example.com', $email->value());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $email = Email::fromString('  test@example.com  ');

        $this->assertSame('test@example.com', $email->value());
    }

    public function testFromStringThrowsExceptionForInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        Email::fromString('not-an-email');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        Email::fromString('');
    }

    public function testFromStringThrowsExceptionForTooLongEmail(): void
    {
        $longEmail = str_repeat('a', 250).'@test.com'; // 259 characters (over 255 limit)

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address cannot exceed 255 characters');

        Email::fromString($longEmail);
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $email1 = Email::fromString('test@example.com');
        $email2 = Email::fromString('test@example.com');

        $this->assertTrue($email1->equals($email2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $email1 = Email::fromString('test@example.com');
        $email2 = Email::fromString('other@example.com');

        $this->assertFalse($email1->equals($email2));
    }

    public function testEqualsIsCaseInsensitive(): void
    {
        $email1 = Email::fromString('Test@Example.COM');
        $email2 = Email::fromString('test@example.com');

        $this->assertTrue($email1->equals($email2));
    }

    public function testToStringReturnsValue(): void
    {
        $email = Email::fromString('test@example.com');

        $this->assertSame('test@example.com', (string) $email);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidEmailProvider')]
    public function testFromStringRejectsInvalidEmails(string $invalidEmail): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Email::fromString($invalidEmail);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            [''],
            ['testexample.com'],
            ['test@@example.com'],
            ['test@'],
            ['@example.com'],
            ['test @example.com'],
        ];
    }
}
