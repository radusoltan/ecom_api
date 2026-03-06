<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\Command\ActivateTenantCommand;
use App\Tenant\Application\Command\ActivateTenantCommandHandler;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActivateTenantCommandHandlerTest extends KernelTestCase
{
    use TenantTestTrait;

    private TenantRepositoryInterface $tenantRepository;
    private ActivateTenantCommandHandler $handler;
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();

        // Clear EntityManager identity map to prevent stale entities from prior tests
        $em = $container->get('doctrine.orm.entity_manager');
        $em->clear();

        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->handler = $container->get(ActivateTenantCommandHandler::class);

        // Use a fresh unique tenant ID per test to avoid ext_translations unique constraint violations
        $this->tenantId = TenantId::generate();
        $this->setTenantContext($this->tenantId->toString());
    }

    protected function tearDown(): void
    {
        // Clean up test tenants to avoid slug unique constraint violations
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $conn = $em->getConnection();
        // Bypass RLS so we can delete regardless of which tenant context is active
        $conn->executeStatement("SET app.bypass_rls = 'true'");
        $conn->executeStatement('DELETE FROM ext_translations WHERE foreign_key = ?', [$this->tenantId->toString()]);
        $conn->executeStatement('DELETE FROM tenants WHERE id = ?', [$this->tenantId->toString()]);
        $conn->executeStatement("SET app.bypass_rls = 'false'");
        $em->clear();

        parent::tearDown();
    }

    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    private function uniqueName(string $base): string
    {
        return $base.' '.substr(uniqid(), -6);
    }

    public function testItActivatesInactiveTenant(): void
    {
        // Arrange - Use the default test tenant (required for RLS)
        $email = $this->generateUniqueEmail();
        $tenant = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($this->uniqueName('Test Company')),
            ownerEmail: Email::fromString($email),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: new \DateTimeImmutable()
        );

        // Deactivate the tenant
        $tenant->deactivate();
        $this->tenantRepository->save($tenant);

        $tenantId = $tenant->id()->toString();

        // Verify tenant is inactive before activation
        $inactiveTenant = $this->tenantRepository->findById(TenantId::fromString($tenantId));
        $this->assertNotNull($inactiveTenant);
        $this->assertFalse($inactiveTenant->status()->isActive());

        // Act
        $command = new ActivateTenantCommand($tenantId);
        $this->handler->__invoke($command);

        // Assert - Verify tenant is now active
        $activatedTenant = $this->tenantRepository->findById(TenantId::fromString($tenantId));

        $this->assertNotNull($activatedTenant);
        $this->assertTrue($activatedTenant->status()->isActive());
        $this->assertSame('active', $activatedTenant->status()->value());
    }

    public function testItThrowsExceptionWhenTenantDoesNotExist(): void
    {
        // Arrange
        $nonExistentTenantId = TenantId::generate()->toString();
        $command = new ActivateTenantCommand($nonExistentTenantId);

        // Assert & Act
        $this->expectException(TenantNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Tenant with ID "%s" not found', $nonExistentTenantId));

        $this->handler->__invoke($command);
    }

    public function testItSavesTenantAfterActivation(): void
    {
        // Arrange - Use the default test tenant (required for RLS)
        $email = $this->generateUniqueEmail('admin');
        $name = $this->uniqueName('Acme Corporation');
        $tenant = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($name),
            ownerEmail: Email::fromString($email),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: new \DateTimeImmutable()
        );

        // Deactivate the tenant
        $tenant->deactivate();
        $this->tenantRepository->save($tenant);

        $tenantId = $tenant->id()->toString();

        // Act - Activate tenant
        $command = new ActivateTenantCommand($tenantId);
        $this->handler->__invoke($command);

        // Assert - Retrieve tenant from repository to verify persistence
        $persistedTenant = $this->tenantRepository->findById(TenantId::fromString($tenantId));

        $this->assertNotNull($persistedTenant);
        $this->assertTrue($persistedTenant->status()->isActive());
        $this->assertSame($name, $persistedTenant->name()->value());
        $this->assertSame($email, $persistedTenant->ownerEmail()->value());
    }
}
