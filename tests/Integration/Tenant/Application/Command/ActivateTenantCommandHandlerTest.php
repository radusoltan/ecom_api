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
        self::bootKernel();

        $container = self::getContainer();
        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->handler = $container->get(ActivateTenantCommandHandler::class);

        // Set tenant context for RLS
        $this->setTenantContext($this->getDefaultTenantId()->toString());
    }

    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    public function testItActivatesInactiveTenant(): void
    {
        // Arrange - Create an inactive tenant
        $email = $this->generateUniqueEmail();
        $tenant = Tenant::create(
            TenantName::fromString('Test Company'),
            Email::fromString($email)
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
        // Arrange - Create an inactive tenant
        $email = $this->generateUniqueEmail('admin');
        $tenant = Tenant::create(
            TenantName::fromString('Acme Corporation'),
            Email::fromString($email)
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
        $this->assertSame('Acme Corporation', $persistedTenant->name()->value());
        $this->assertSame($email, $persistedTenant->ownerEmail()->value());
    }
}
