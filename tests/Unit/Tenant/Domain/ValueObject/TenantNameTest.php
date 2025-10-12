<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\ValueObject;

use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\TestCase;

final class TenantNameTest extends TestCase
{
    public function testFromStringCreatesValidTenantName(): void
    {
        $name = TenantName::fromString('Test Company');

        $this->assertInstanceOf(TenantName::class, $name);
        $this->assertSame('Test Company', $name->value());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $name = TenantName::fromString('  Test Company  ');

        $this->assertSame('Test Company', $name->value());
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot be empty');

        TenantName::fromString('');
    }

    public function testFromStringThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot be empty');

        TenantName::fromString('   ');
    }

    public function testFromStringThrowsExceptionForTooShortName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name must be at least 3 characters long');

        TenantName::fromString('AB');
    }

    public function testFromStringThrowsExceptionForTooLongName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot exceed 100 characters');

        TenantName::fromString(str_repeat('A', 101));
    }

    public function testFromStringAcceptsMinimumLength(): void
    {
        $name = TenantName::fromString('ABC');

        $this->assertSame('ABC', $name->value());
    }

    public function testFromStringAcceptsMaximumLength(): void
    {
        $longName = str_repeat('A', 100);
        $name = TenantName::fromString($longName);

        $this->assertSame($longName, $name->value());
    }

    public function testFromStringThrowsExceptionForInvalidCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name can only contain alphanumeric characters and spaces');

        TenantName::fromString('Test@#$%Company');
    }

    public function testFromStringAcceptsValidCharacters(): void
    {
        $validNames = [
            'Test Company',
            'Test Company 123',
            'ABC',
            'My Company 2024',
        ];

        foreach ($validNames as $validName) {
            $name = TenantName::fromString($validName);
            $this->assertSame($validName, $name->value());
        }
    }

    public function testFromStringRejectsPunctuation(): void
    {
        $invalidNames = [
            'Test-Company',
            'Test.Company',
            "Test's Company",
            'Test & Company',
            'Test@Company',
        ];

        foreach ($invalidNames as $invalidName) {
            try {
                TenantName::fromString($invalidName);
                $this->fail("Expected InvalidArgumentException for name: {$invalidName}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('alphanumeric', $e->getMessage());
            }
        }
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $name1 = TenantName::fromString('Test Company');
        $name2 = TenantName::fromString('Test Company');

        $this->assertTrue($name1->equals($name2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $name1 = TenantName::fromString('Test Company');
        $name2 = TenantName::fromString('Other Company');

        $this->assertFalse($name1->equals($name2));
    }

    public function testToStringReturnsValue(): void
    {
        $name = TenantName::fromString('Test Company');

        $this->assertSame('Test Company', (string) $name);
    }
}
