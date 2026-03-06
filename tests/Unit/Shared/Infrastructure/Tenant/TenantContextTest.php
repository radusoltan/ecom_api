<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Tenant;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantContext::class)]
#[Group('shared')]
final class TenantContextTest extends TestCase
{
    private Connection $connection;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->tenantContext = new TenantContext($this->connection);
    }

    #[Test]
    public function itHasNoTenantInitially(): void
    {
        self::assertFalse($this->tenantContext->hasCurrentTenant());
        self::assertNull($this->tenantContext->getCurrentTenantId());
        self::assertNull($this->tenantContext->getTenantId());
    }

    #[Test]
    public function itSetsTenantAndExecutesSqlConfig(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                "SELECT set_config('app.tenant_id', :tenantId, false)",
                ['tenantId' => '00000000-0000-4000-8000-000000000001']
            );

        $this->tenantContext->setCurrentTenant($tenantId);

        self::assertTrue($this->tenantContext->hasCurrentTenant());
        self::assertTrue($tenantId->equals($this->tenantContext->getCurrentTenantId()));
        self::assertSame('00000000-0000-4000-8000-000000000001', $this->tenantContext->getTenantId());
    }

    #[Test]
    public function itClearsTenantAndResetsDbConfig(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->connection->method('executeStatement');

        $this->tenantContext->setCurrentTenant($tenantId);
        self::assertTrue($this->tenantContext->hasCurrentTenant());

        $this->tenantContext->clearCurrentTenant();

        self::assertFalse($this->tenantContext->hasCurrentTenant());
        self::assertNull($this->tenantContext->getCurrentTenantId());
        self::assertNull($this->tenantContext->getTenantId());
    }

    #[Test]
    public function itIgnoresConnectionErrorsDuringClear(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        // First call (setCurrentTenant) succeeds, second call (clearCurrentTenant RESET) throws
        $this->connection->method('executeStatement')
            ->willReturnOnConsecutiveCalls(
                1,
                self::throwException(new \Exception('Connection lost'))
            );

        $this->tenantContext->setCurrentTenant($tenantId);

        // Should NOT throw
        $this->tenantContext->clearCurrentTenant();

        self::assertFalse($this->tenantContext->hasCurrentTenant());
    }

    #[Test]
    public function itCanSetMultipleDifferentTenants(): void
    {
        $tenant1 = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $tenant2 = TenantId::fromString('00000000-0000-4000-8000-000000000002');

        $this->connection->method('executeStatement');

        $this->tenantContext->setCurrentTenant($tenant1);
        self::assertTrue($tenant1->equals($this->tenantContext->getCurrentTenantId()));

        $this->tenantContext->setCurrentTenant($tenant2);
        self::assertTrue($tenant2->equals($this->tenantContext->getCurrentTenantId()));
    }
}
