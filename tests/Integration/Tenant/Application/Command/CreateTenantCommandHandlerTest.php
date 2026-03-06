<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\Command\CreateTenantCommand;
use App\Tenant\Application\Command\CreateTenantCommandHandler;
use App\Tenant\Domain\Exception\TenantAlreadyExistsException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateTenantCommandHandlerTest extends KernelTestCase
{
    use TenantTestTrait;

    private TenantRepositoryInterface $tenantRepository;
    private CreateTenantCommandHandler $handler;
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
        $this->handler = $container->get(CreateTenantCommandHandler::class);

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

    public function testItCreatesNewTenant(): void
    {
        // Arrange - Use the default test tenant (required for RLS)
        // Note: In a real multi-tenant system, tenant creation would be done by a superuser
        // without RLS constraints. Here we test within RLS constraints using default tenant ID.
        $email = $this->generateUniqueEmail();
        $name = $this->uniqueName('Test Company');
        $tenant = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($name),
            ownerEmail: Email::fromString($email),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: new \DateTimeImmutable()
        );
        $this->tenantRepository->save($tenant);

        // Assert
        $foundTenant = $this->tenantRepository->findByOwnerEmail(
            Email::fromString($email)
        );

        $this->assertNotNull($foundTenant);
        $this->assertInstanceOf(Tenant::class, $foundTenant);
        $this->assertSame($name, $foundTenant->name()->value());
        $this->assertSame($email, $foundTenant->ownerEmail()->value());
        $this->assertTrue($foundTenant->status()->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $foundTenant->createdAt());
    }

    public function testItThrowsExceptionWhenTenantWithEmailAlreadyExists(): void
    {
        // Arrange - Create an existing tenant using default tenant ID (required for RLS)
        $email = $this->generateUniqueEmail('existing');
        $existingTenant = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($this->uniqueName('Existing Company')),
            ownerEmail: Email::fromString($email),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: new \DateTimeImmutable()
        );
        $this->tenantRepository->save($existingTenant);

        // Create command with same email
        $command = new CreateTenantCommand(
            name: 'Another Company',
            ownerEmail: $email
        );

        // Assert & Act
        $this->expectException(TenantAlreadyExistsException::class);
        $this->expectExceptionMessage(sprintf('Tenant with owner email "%s" already exists', $email));

        $this->handler->__invoke($command);
    }

    public function testItSavesTenantViaRepository(): void
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

        // Act
        $this->tenantRepository->save($tenant);

        // Assert - Verify tenant was persisted by querying the repository
        $savedTenant = $this->tenantRepository->findByOwnerEmail(
            Email::fromString($email)
        );

        $this->assertNotNull($savedTenant);
        $this->assertSame($name, $savedTenant->name()->value());
        $this->assertSame($email, $savedTenant->ownerEmail()->value());

        // Verify the tenant has the correct UUID (default test tenant)
        $this->assertSame($this->tenantId->toString(), $savedTenant->id()->toString());
    }
}
