<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Event;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\TestCase;

final class TenantCreatedTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenantName = TenantName::fromString('Test Company');
        $ownerEmail = Email::fromString('owner@test.com');

        // Act
        $event = new TenantCreated($tenantId, $tenantName, $ownerEmail);

        // Assert
        $this->assertSame($tenantId, $event->tenantId);
        $this->assertSame($tenantName, $event->name);
        $this->assertSame($ownerEmail, $event->ownerEmail);
    }

    public function testTenantIdPropertyReturnsCorrectValue(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $tenantName = TenantName::fromString('Test Company');
        $ownerEmail = Email::fromString('owner@test.com');
        $event = new TenantCreated($tenantId, $tenantName, $ownerEmail);

        // Act
        $result = $event->tenantId;

        // Assert
        $this->assertTrue($result->equals($tenantId));
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $result->toString());
    }

    public function testNamePropertyReturnsCorrectValue(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenantName = TenantName::fromString('My Company');
        $ownerEmail = Email::fromString('owner@test.com');
        $event = new TenantCreated($tenantId, $tenantName, $ownerEmail);

        // Act
        $result = $event->name;

        // Assert
        $this->assertTrue($result->equals($tenantName));
        $this->assertSame('My Company', $result->value());
    }

    public function testOwnerEmailPropertyReturnsCorrectValue(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenantName = TenantName::fromString('Test Company');
        $ownerEmail = Email::fromString('test@example.com');
        $event = new TenantCreated($tenantId, $tenantName, $ownerEmail);

        // Act
        $result = $event->ownerEmail;

        // Assert
        $this->assertTrue($result->equals($ownerEmail));
        $this->assertSame('test@example.com', $result->value());
    }

    public function testEventIsImmutable(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenantName = TenantName::fromString('Test Company');
        $ownerEmail = Email::fromString('owner@test.com');
        $event = new TenantCreated($tenantId, $tenantName, $ownerEmail);

        // Act - get values multiple times
        $id1 = $event->tenantId;
        $id2 = $event->tenantId;
        $name1 = $event->name;
        $name2 = $event->name;
        $email1 = $event->ownerEmail;
        $email2 = $event->ownerEmail;

        // Assert - same instances returned
        $this->assertSame($id1, $id2);
        $this->assertSame($name1, $name2);
        $this->assertSame($email1, $email2);
    }
}
