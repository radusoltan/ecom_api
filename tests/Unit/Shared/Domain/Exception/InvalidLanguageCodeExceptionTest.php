<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\InvalidLanguageCodeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidLanguageCodeException::class)]
final class InvalidLanguageCodeExceptionTest extends TestCase
{
    // -------
    // unsupported()
    // -------

    #[Test]
    public function unsupportedCreatesExceptionWithCode(): void
    {
        $exception = InvalidLanguageCodeException::unsupported('xx');

        self::assertInstanceOf(InvalidLanguageCodeException::class, $exception);
        self::assertInstanceOf(\DomainException::class, $exception);
        self::assertStringContainsString('"xx"', $exception->getMessage());
        self::assertStringContainsString('not supported', $exception->getMessage());
    }

    #[Test]
    public function unsupportedMessageListsSupportedLanguages(): void
    {
        $exception = InvalidLanguageCodeException::unsupported('zz');

        // The message mentions supported languages
        self::assertStringContainsString('en', $exception->getMessage());
        self::assertStringContainsString('fr', $exception->getMessage());
        self::assertStringContainsString('de', $exception->getMessage());
    }

    // -------
    // empty()
    // -------

    #[Test]
    public function emptyCreatesExceptionWithCannotBeEmptyMessage(): void
    {
        $exception = InvalidLanguageCodeException::empty();

        self::assertInstanceOf(InvalidLanguageCodeException::class, $exception);
        self::assertStringContainsString('cannot be empty', $exception->getMessage());
    }

    // -------
    // invalidFormat()
    // -------

    #[Test]
    public function invalidFormatCreatesExceptionWithCode(): void
    {
        $exception = InvalidLanguageCodeException::invalidFormat('EN-US');

        self::assertInstanceOf(InvalidLanguageCodeException::class, $exception);
        self::assertStringContainsString('"EN-US"', $exception->getMessage());
        self::assertStringContainsString('invalid format', $exception->getMessage());
    }

    #[Test]
    public function invalidFormatMessageDescribesExpectedFormat(): void
    {
        $exception = InvalidLanguageCodeException::invalidFormat('123');

        self::assertStringContainsString('ISO 639-1', $exception->getMessage());
    }

    // -------
    // Inheritance
    // -------

    #[Test]
    public function itExtendsDomainException(): void
    {
        $exception = InvalidLanguageCodeException::unsupported('xx');

        self::assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    public function itImplementsThrowableInterface(): void
    {
        $exception = InvalidLanguageCodeException::empty();

        self::assertInstanceOf(\Throwable::class, $exception);
    }
}
