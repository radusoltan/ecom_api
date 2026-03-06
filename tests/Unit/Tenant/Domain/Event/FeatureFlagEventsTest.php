<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\FeatureFlagConfigurationUpdated;
use App\Tenant\Domain\Event\FeatureFlagCreated;
use App\Tenant\Domain\Event\FeatureFlagToggled;
use App\Tenant\Domain\Model\FeatureFlagId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureFlagCreated::class)]
#[CoversClass(FeatureFlagToggled::class)]
#[CoversClass(FeatureFlagConfigurationUpdated::class)]
final class FeatureFlagEventsTest extends TestCase
{
    private FeatureFlagId $flagId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->flagId = FeatureFlagId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    #[Test]
    public function featureFlagCreatedEnabled(): void
    {
        $event = new FeatureFlagCreated($this->flagId, $this->tenantId, 'dark_mode', true);

        self::assertSame($this->flagId, $event->flagId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame('dark_mode', $event->featureName);
        self::assertTrue($event->enabled);
    }

    #[Test]
    public function featureFlagCreatedDisabled(): void
    {
        $event = new FeatureFlagCreated($this->flagId, $this->tenantId, 'beta_feature', false);

        self::assertFalse($event->enabled);
        self::assertSame('beta_feature', $event->featureName);
    }

    #[Test]
    public function featureFlagToggledOn(): void
    {
        $event = new FeatureFlagToggled($this->flagId, $this->tenantId, 'new_checkout', true);

        self::assertSame($this->flagId, $event->flagId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame('new_checkout', $event->featureName);
        self::assertTrue($event->enabled);
    }

    #[Test]
    public function featureFlagToggledOff(): void
    {
        $event = new FeatureFlagToggled($this->flagId, $this->tenantId, 'new_checkout', false);

        self::assertFalse($event->enabled);
    }

    #[Test]
    public function featureFlagConfigurationUpdated(): void
    {
        $event = new FeatureFlagConfigurationUpdated($this->flagId, $this->tenantId, 'loyalty_program');

        self::assertSame($this->flagId, $event->flagId);
        self::assertSame($this->tenantId, $event->tenantId);
        self::assertSame('loyalty_program', $event->featureName);
    }
}
