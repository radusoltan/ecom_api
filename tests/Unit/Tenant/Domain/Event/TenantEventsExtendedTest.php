<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Event;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\FeatureFlagConfigurationUpdated;
use App\Tenant\Domain\Event\FeatureFlagCreated;
use App\Tenant\Domain\Event\FeatureFlagToggled;
use App\Tenant\Domain\Event\TenantReactivated;
use App\Tenant\Domain\Event\TenantSuspended;
use App\Tenant\Domain\Event\TenantUpdated;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\TestCase;

final class TenantEventsExtendedTest extends TestCase
{
    public function testTenantSuspendedExposesProperties(): void
    {
        $tenantId = TenantId::generate();
        $event = new TenantSuspended($tenantId);

        $this->assertSame($tenantId, $event->tenantId);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    public function testTenantReactivatedExposesProperties(): void
    {
        $tenantId = TenantId::generate();
        $event = new TenantReactivated($tenantId);

        $this->assertSame($tenantId, $event->tenantId);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    public function testTenantUpdatedExposesProperties(): void
    {
        $tenantId = TenantId::generate();
        $name = TenantName::fromString('Updated Corp');
        $email = Email::fromString('updated@corp.com');
        $event = new TenantUpdated($tenantId, $name, $email);

        $this->assertSame($tenantId, $event->tenantId);
        $this->assertTrue($name->equals($event->name));
        $this->assertTrue($email->equals($event->ownerEmail));
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    public function testFeatureFlagCreatedExposesProperties(): void
    {
        $flagId = FeatureFlagId::generate();
        $tenantId = TenantId::generate();
        $event = new FeatureFlagCreated($flagId, $tenantId, 'dark_mode', true);

        $this->assertSame($flagId, $event->flagId);
        $this->assertSame($tenantId, $event->tenantId);
        $this->assertSame('dark_mode', $event->featureName);
        $this->assertTrue($event->enabled);
    }

    public function testFeatureFlagToggledExposesProperties(): void
    {
        $flagId = FeatureFlagId::generate();
        $tenantId = TenantId::generate();
        $event = new FeatureFlagToggled($flagId, $tenantId, 'dark_mode', false);

        $this->assertSame($flagId, $event->flagId);
        $this->assertSame($tenantId, $event->tenantId);
        $this->assertSame('dark_mode', $event->featureName);
        $this->assertFalse($event->enabled);
    }

    public function testFeatureFlagConfigurationUpdatedExposesProperties(): void
    {
        $flagId = FeatureFlagId::generate();
        $tenantId = TenantId::generate();
        $event = new FeatureFlagConfigurationUpdated($flagId, $tenantId, 'checkout_v2');

        $this->assertSame($flagId, $event->flagId);
        $this->assertSame($tenantId, $event->tenantId);
        $this->assertSame('checkout_v2', $event->featureName);
    }
}
