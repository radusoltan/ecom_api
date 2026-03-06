<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Query;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Application\Query\GetAllTenantsQuery;
use App\Tenant\Application\Query\GetAllTenantsQueryHandler;
use App\Tenant\Application\Query\GetFeatureFlagQuery;
use App\Tenant\Application\Query\GetFeatureFlagQueryHandler;
use App\Tenant\Application\Query\GetFeatureFlagsQuery;
use App\Tenant\Application\Query\GetFeatureFlagsQueryHandler;
use App\Tenant\Application\Query\GetTenantByIdQuery;
use App\Tenant\Application\Query\GetTenantByIdQueryHandler;
use App\Tenant\Application\Query\GetTenantByOwnerEmailQuery;
use App\Tenant\Application\Query\GetTenantByOwnerEmailQueryHandler;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(GetAllTenantsQueryHandler::class)]
#[CoversClass(GetTenantByIdQueryHandler::class)]
#[CoversClass(GetTenantByOwnerEmailQueryHandler::class)]
#[CoversClass(GetFeatureFlagsQueryHandler::class)]
#[CoversClass(GetFeatureFlagQueryHandler::class)]
final class TenantQueryHandlersTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private TenantRepositoryInterface&MockObject $tenantRepository;
    private FeatureFlagRepositoryInterface&MockObject $featureFlagRepository;
    private LoggerInterface&MockObject $logger;
    private PerformanceProfiler $profiler;

    protected function setUp(): void
    {
        $this->tenantRepository = $this->createMock(TenantRepositoryInterface::class);
        $this->featureFlagRepository = $this->createMock(FeatureFlagRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeTenant(?string $name = 'Acme Corp', ?string $email = 'owner@acme.com'): Tenant
    {
        $tenant = Tenant::create(
            TenantName::fromString($name),
            Email::fromString($email),
        );
        $tenant->popEvents();

        return $tenant;
    }

    private function makeFeatureFlag(bool $enabled = true, string $name = 'checkout'): FeatureFlag
    {
        return FeatureFlag::create(
            id: FeatureFlagId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            featureName: $name,
            enabled: $enabled,
            configuration: ['limit' => 100],
        );
    }

    // -----------------------------------------------------------------------
    // GetAllTenantsQueryHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenNoTenantsExist(): void
    {
        $this->tenantRepository->method('findAll')->willReturn([]);

        $handler = new GetAllTenantsQueryHandler(
            $this->tenantRepository,
            $this->profiler,
            $this->logger,
        );

        $result = ($handler)(new GetAllTenantsQuery());

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsMappedDTOsForAllTenants(): void
    {
        $tenant1 = $this->makeTenant('Store A', 'a@store.com');
        $tenant2 = $this->makeTenant('Store B', 'b@store.com');

        $this->tenantRepository->method('findAll')->willReturn([$tenant1, $tenant2]);

        $handler = new GetAllTenantsQueryHandler(
            $this->tenantRepository,
            $this->profiler,
            $this->logger,
        );

        $result = ($handler)(new GetAllTenantsQuery());

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(TenantDTO::class, $result);
        self::assertSame('Store A', $result[0]->name);
        self::assertSame('Store B', $result[1]->name);
    }

    #[Test]
    public function itRethrowsRepositoryExceptionInGetAll(): void
    {
        $this->tenantRepository
            ->method('findAll')
            ->willThrowException(new \RuntimeException('DB unavailable'));

        $handler = new GetAllTenantsQueryHandler(
            $this->tenantRepository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB unavailable');

        ($handler)(new GetAllTenantsQuery());
    }

    // -----------------------------------------------------------------------
    // GetTenantByIdQueryHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTenantDTOForExistingId(): void
    {
        $tenant = $this->makeTenant();
        $this->tenantRepository->method('findById')->willReturn($tenant);

        $handler = new GetTenantByIdQueryHandler(
            $this->tenantRepository,
            $this->profiler,
            $this->logger,
        );

        $result = ($handler)(new GetTenantByIdQuery(self::TENANT_ID));

        self::assertInstanceOf(TenantDTO::class, $result);
        self::assertSame('Acme Corp', $result->name);
        self::assertSame('owner@acme.com', $result->ownerEmail);
    }

    #[Test]
    public function itThrowsTenantNotFoundForMissingId(): void
    {
        $this->tenantRepository->method('findById')->willReturn(null);

        $handler = new GetTenantByIdQueryHandler(
            $this->tenantRepository,
            $this->profiler,
            $this->logger,
        );

        $this->expectException(TenantNotFoundException::class);

        ($handler)(new GetTenantByIdQuery(self::TENANT_ID));
    }

    // -----------------------------------------------------------------------
    // GetTenantByOwnerEmailQueryHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTenantDTOForExistingOwnerEmail(): void
    {
        $tenant = $this->makeTenant('Acme', 'owner@acme.com');
        $this->tenantRepository->method('findByOwnerEmail')->willReturn($tenant);

        $handler = new GetTenantByOwnerEmailQueryHandler($this->tenantRepository);

        $result = ($handler)(new GetTenantByOwnerEmailQuery('owner@acme.com'));

        self::assertInstanceOf(TenantDTO::class, $result);
        self::assertSame('owner@acme.com', $result->ownerEmail);
    }

    #[Test]
    public function itThrowsTenantNotFoundForMissingOwnerEmail(): void
    {
        $this->tenantRepository->method('findByOwnerEmail')->willReturn(null);

        $handler = new GetTenantByOwnerEmailQueryHandler($this->tenantRepository);

        $this->expectException(TenantNotFoundException::class);

        ($handler)(new GetTenantByOwnerEmailQuery('ghost@example.com'));
    }

    // -----------------------------------------------------------------------
    // GetFeatureFlagsQueryHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAllFeatureFlagsForTenant(): void
    {
        $flag1 = $this->makeFeatureFlag(true, 'checkout');
        $flag2 = $this->makeFeatureFlag(false, 'wishlist');

        $this->featureFlagRepository
            ->expects(self::once())
            ->method('findAllByTenant')
            ->willReturn([$flag1, $flag2]);

        $handler = new GetFeatureFlagsQueryHandler($this->featureFlagRepository);

        $result = ($handler)(new GetFeatureFlagsQuery(self::TENANT_ID));

        self::assertCount(2, $result);
        self::assertSame('checkout', $result[0]->featureName());
        self::assertSame('wishlist', $result[1]->featureName());
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoFeatureFlagsExist(): void
    {
        $this->featureFlagRepository->method('findAllByTenant')->willReturn([]);

        $handler = new GetFeatureFlagsQueryHandler($this->featureFlagRepository);

        $result = ($handler)(new GetFeatureFlagsQuery(self::TENANT_ID));

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // GetFeatureFlagQueryHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFeatureFlagWhenFound(): void
    {
        $flag = $this->makeFeatureFlag(true, 'checkout');

        $this->featureFlagRepository
            ->expects(self::once())
            ->method('findByTenantAndFeature')
            ->willReturn($flag);

        $handler = new GetFeatureFlagQueryHandler($this->featureFlagRepository);

        $result = ($handler)(new GetFeatureFlagQuery(self::TENANT_ID, 'checkout'));

        self::assertInstanceOf(FeatureFlag::class, $result);
        self::assertSame('checkout', $result->featureName());
        self::assertTrue($result->isEnabled());
    }

    #[Test]
    public function itReturnsNullWhenFeatureFlagNotFound(): void
    {
        $this->featureFlagRepository->method('findByTenantAndFeature')->willReturn(null);

        $handler = new GetFeatureFlagQueryHandler($this->featureFlagRepository);

        $result = ($handler)(new GetFeatureFlagQuery(self::TENANT_ID, 'nonexistent'));

        self::assertNull($result);
    }
}
