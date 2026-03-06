<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\Exception;

use App\User\Domain\Exception\EmailAlreadyExistsException;
use App\User\Domain\Exception\UsernameAlreadyExistsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAlreadyExistsException::class)]
#[CoversClass(UsernameAlreadyExistsException::class)]
final class UserExceptionsTest extends TestCase
{
    #[Test]
    public function emailAlreadyExists(): void
    {
        $e = EmailAlreadyExistsException::forEmail('john@example.com');

        self::assertInstanceOf(\DomainException::class, $e);
        self::assertStringContainsString('john@example.com', $e->getMessage());
        self::assertStringContainsString('already exists', $e->getMessage());
    }

    #[Test]
    public function usernameAlreadyExists(): void
    {
        $e = UsernameAlreadyExistsException::forUsername('johndoe');

        self::assertInstanceOf(\DomainException::class, $e);
        self::assertStringContainsString('johndoe', $e->getMessage());
        self::assertStringContainsString('already exists', $e->getMessage());
    }
}
