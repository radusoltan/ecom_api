<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Application\Command\CreateTenantCommand;
use App\Tenant\Application\Command\CreateTenantCommandHandler;
use App\Tenant\Domain\Exception\TenantAlreadyExistsException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateTenantCommandHandlerTest extends KernelTestCase
{
    private TenantRepositoryInterface $tenantRepository;
    private CreateTenantCommandHandler $handler;
    private static int $counter = 0;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->handler = $container->get(CreateTenantCommandHandler::class);
    }

    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    public function testItCreatesNewTenant(): void
    {
        // Arrange
        $email = $this->generateUniqueEmail();
        $command = new CreateTenantCommand(
            name: 'Test Company',
            ownerEmail: $email
        );

        // Act
        $this->handler->__invoke($command);

        // Assert
        $tenant = $this->tenantRepository->findByOwnerEmail(
            Email::fromString($email)
        );

        $this->assertNotNull($tenant);
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertSame('Test Company', $tenant->name()->value());
        $this->assertSame($email, $tenant->ownerEmail()->value());
        $this->assertTrue($tenant->status()->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $tenant->createdAt());
    }

    public function testItThrowsExceptionWhenTenantWithEmailAlreadyExists(): void
    {
        // Arrange - Create an existing tenant
        $email = $this->generateUniqueEmail('existing');
        $existingTenant = Tenant::create(
            TenantName::fromString('Existing Company'),
            Email::fromString($email)
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
        // Arrange
        $email = $this->generateUniqueEmail('admin');
        $command = new CreateTenantCommand(
            name: 'Acme Corporation',
            ownerEmail: $email
        );

        // Act
        $this->handler->__invoke($command);

        // Assert - Verify tenant was persisted by querying the repository
        $savedTenant = $this->tenantRepository->findByOwnerEmail(
            Email::fromString($email)
        );

        $this->assertNotNull($savedTenant);
        $this->assertSame('Acme Corporation', $savedTenant->name()->value());
        $this->assertSame($email, $savedTenant->ownerEmail()->value());

        // Verify the tenant has a valid UUID
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $savedTenant->id()->toString()
        );
    }
}
