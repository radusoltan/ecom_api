<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Application\Query;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Application\Query\GetTenantByIdQuery;
use App\Tenant\Application\Query\GetTenantByIdQueryHandler;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetTenantByIdQueryHandlerTest extends KernelTestCase
{
    private TenantRepositoryInterface $tenantRepository;
    private GetTenantByIdQueryHandler $handler;
    private static int $counter = 0;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->handler = $container->get(GetTenantByIdQueryHandler::class);
    }

    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    public function testItRetrievesTenantById(): void
    {
        // Arrange - Create a tenant
        $email = $this->generateUniqueEmail();
        $tenant = Tenant::create(
            TenantName::fromString('Test Company'),
            Email::fromString($email)
        );
        $this->tenantRepository->save($tenant);

        $tenantId = $tenant->id()->toString();

        // Act
        $query = new GetTenantByIdQuery($tenantId);
        $result = $this->handler->__invoke($query);

        // Assert
        $this->assertInstanceOf(TenantDTO::class, $result);
        $this->assertSame($tenantId, $result->id);
        $this->assertSame('Test Company', $result->name);
        $this->assertSame($email, $result->ownerEmail);
        $this->assertSame('active', $result->status);
        $this->assertNotEmpty($result->createdAt);
    }

    public function testItThrowsExceptionWhenTenantDoesNotExist(): void
    {
        // Arrange
        $nonExistentTenantId = TenantId::generate()->toString();
        $query = new GetTenantByIdQuery($nonExistentTenantId);

        // Assert & Act
        $this->expectException(TenantNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Tenant with ID "%s" not found', $nonExistentTenantId));

        $this->handler->__invoke($query);
    }

    public function testReturnedDTOHasCorrectData(): void
    {
        // Arrange - Create a tenant with specific data
        $email = $this->generateUniqueEmail('admin');
        $tenant = Tenant::create(
            TenantName::fromString('Acme Corporation'),
            Email::fromString($email)
        );
        $this->tenantRepository->save($tenant);

        $tenantId = $tenant->id()->toString();
        $createdAt = $tenant->createdAt();

        // Act
        $query = new GetTenantByIdQuery($tenantId);
        $dto = $this->handler->__invoke($query);

        // Assert - Verify all DTO properties match the tenant
        $this->assertSame($tenantId, $dto->id);
        $this->assertSame('Acme Corporation', $dto->name);
        $this->assertSame($email, $dto->ownerEmail);
        $this->assertSame('active', $dto->status);
        $this->assertSame($createdAt->format('Y-m-d H:i:s'), $dto->createdAt);

        // Verify DTO structure
        $this->assertIsString($dto->id);
        $this->assertIsString($dto->name);
        $this->assertIsString($dto->ownerEmail);
        $this->assertIsString($dto->status);
        $this->assertIsString($dto->createdAt);
    }
}
