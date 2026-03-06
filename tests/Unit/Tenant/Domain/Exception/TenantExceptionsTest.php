<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Exception;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Exception\TenantAlreadyExistsException;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Exception\TranslationQuotaExceededException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantNotFoundException::class)]
#[CoversClass(TenantAlreadyExistsException::class)]
#[CoversClass(TranslationQuotaExceededException::class)]
final class TenantExceptionsTest extends TestCase
{
    #[Test]
    public function tenantNotFoundWithId(): void
    {
        $id = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $e = TenantNotFoundException::withId($id);

        self::assertInstanceOf(\DomainException::class, $e);
        self::assertStringContainsString($id->toString(), $e->getMessage());
    }

    #[Test]
    public function tenantNotFoundWithEmail(): void
    {
        $e = TenantNotFoundException::withEmail('admin@example.com');

        self::assertStringContainsString('admin@example.com', $e->getMessage());
    }

    #[Test]
    public function tenantAlreadyExistsWithId(): void
    {
        $id = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $e = TenantAlreadyExistsException::withId($id);

        self::assertInstanceOf(\DomainException::class, $e);
        self::assertStringContainsString($id->toString(), $e->getMessage());
        self::assertStringContainsString('already exists', $e->getMessage());
    }

    #[Test]
    public function tenantAlreadyExistsWithEmail(): void
    {
        $e = TenantAlreadyExistsException::withEmail('dup@example.com');

        self::assertStringContainsString('dup@example.com', $e->getMessage());
    }

    #[Test]
    public function translationQuotaExceeded(): void
    {
        $e = TranslationQuotaExceededException::forTenant('tenant-1', 1000, 1050);

        self::assertInstanceOf(\DomainException::class, $e);
        self::assertStringContainsString('tenant-1', $e->getMessage());
        self::assertStringContainsString('1000', $e->getMessage());
        self::assertStringContainsString('1050', $e->getMessage());
    }
}
