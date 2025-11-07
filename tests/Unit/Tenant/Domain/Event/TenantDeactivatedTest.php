<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\TenantDeactivated;
use PHPUnit\Framework\TestCase;

final class TenantDeactivatedTest extends TestCase
{
    public function testConstructorSetsTenantId(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        // Act
        $event = new TenantDeactivated($tenantId);

        // Assert
        $this->assertSame($tenantId, $event->tenantId);
    }

    public function testTenantIdPropertyReturnsCorrectValue(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $event = new TenantDeactivated($tenantId);

        // Act
        $result = $event->tenantId;

        // Assert
        $this->assertTrue($result->equals($tenantId));
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $result->toString());
    }

    public function testEventIsImmutable(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $event = new TenantDeactivated($tenantId);

        // Act - get value multiple times
        $id1 = $event->tenantId;
        $id2 = $event->tenantId;

        // Assert - same instance returned
        $this->assertSame($id1, $id2);
    }

    public function testMultipleEventsWithDifferentIds(): void
    {
        // Arrange
        $tenantId1 = TenantId::generate();
        $tenantId2 = TenantId::generate();

        // Act
        $event1 = new TenantDeactivated($tenantId1);
        $event2 = new TenantDeactivated($tenantId2);

        // Assert
        $this->assertSame($tenantId1, $event1->tenantId);
        $this->assertSame($tenantId2, $event2->tenantId);
        $this->assertFalse($event1->tenantId->equals($event2->tenantId));
    }
}
