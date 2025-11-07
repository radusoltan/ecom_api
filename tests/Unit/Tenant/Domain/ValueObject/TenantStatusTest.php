<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\ValueObject;

use App\Tenant\Domain\ValueObject\TenantStatus;
use PHPUnit\Framework\TestCase;

final class TenantStatusTest extends TestCase
{
    public function testActiveCreatesActiveStatus(): void
    {
        $status = TenantStatus::active();

        $this->assertInstanceOf(TenantStatus::class, $status);
        $this->assertSame('active', $status->value());
        $this->assertTrue($status->isActive());
    }

    public function testInactiveCreatesInactiveStatus(): void
    {
        $status = TenantStatus::inactive();

        $this->assertInstanceOf(TenantStatus::class, $status);
        $this->assertSame('inactive', $status->value());
        $this->assertFalse($status->isActive());
    }

    public function testFromStringCreatesActiveStatus(): void
    {
        $status = TenantStatus::fromString('active');

        $this->assertTrue($status->isActive());
        $this->assertSame('active', $status->value());
    }

    public function testFromStringCreatesInactiveStatus(): void
    {
        $status = TenantStatus::fromString('inactive');

        $this->assertFalse($status->isActive());
        $this->assertSame('inactive', $status->value());
    }

    public function testFromStringThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant status: "invalid". Must be "active", "inactive", or "suspended"');

        TenantStatus::fromString('invalid');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant status');

        TenantStatus::fromString('');
    }

    public function testEqualsReturnsTrueForSameStatus(): void
    {
        $status1 = TenantStatus::active();
        $status2 = TenantStatus::active();

        $this->assertTrue($status1->equals($status2));
    }

    public function testEqualsReturnsFalseForDifferentStatus(): void
    {
        $status1 = TenantStatus::active();
        $status2 = TenantStatus::inactive();

        $this->assertFalse($status1->equals($status2));
    }

    public function testToStringReturnsValue(): void
    {
        $activeStatus = TenantStatus::active();
        $inactiveStatus = TenantStatus::inactive();

        $this->assertSame('active', (string) $activeStatus);
        $this->assertSame('inactive', (string) $inactiveStatus);
    }

    public function testSuspendedCreatesSuspendedStatus(): void
    {
        $status = TenantStatus::suspended();

        $this->assertInstanceOf(TenantStatus::class, $status);
        $this->assertSame('suspended', $status->value());
        $this->assertTrue($status->isSuspended());
        $this->assertFalse($status->isActive());
        $this->assertFalse($status->isInactive());
    }

    public function testFromStringCreatesSuspendedStatus(): void
    {
        $status = TenantStatus::fromString('suspended');

        $this->assertTrue($status->isSuspended());
        $this->assertFalse($status->isActive());
        $this->assertFalse($status->isInactive());
        $this->assertSame('suspended', $status->value());
    }

    public function testIsSuspendedReturnsFalseForActiveStatus(): void
    {
        $status = TenantStatus::active();

        $this->assertFalse($status->isSuspended());
        $this->assertTrue($status->isActive());
    }

    public function testIsSuspendedReturnsFalseForInactiveStatus(): void
    {
        $status = TenantStatus::inactive();

        $this->assertFalse($status->isSuspended());
        $this->assertTrue($status->isInactive());
    }

    public function testToStringReturnsSuspendedValue(): void
    {
        $status = TenantStatus::suspended();

        $this->assertSame('suspended', (string) $status);
    }
}
