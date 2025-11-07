<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Application\Query;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Application\Query\GetAllTenantsQuery;
use App\Tenant\Application\Query\GetAllTenantsQueryHandler;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetAllTenantsQueryHandlerTest extends KernelTestCase
{
    private TenantRepositoryInterface $tenantRepository;
    private GetAllTenantsQueryHandler $handler;
    private static int $counter = 0;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->handler = $container->get(GetAllTenantsQueryHandler::class);
    }

    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    public function testItRetrievesAllTenants(): void
    {
        // Arrange - Create multiple tenants
        $email1 = $this->generateUniqueEmail('first');
        $tenant1 = Tenant::create(
            TenantName::fromString('First Company'),
            Email::fromString($email1)
        );
        $this->tenantRepository->save($tenant1);

        $email2 = $this->generateUniqueEmail('second');
        $tenant2 = Tenant::create(
            TenantName::fromString('Second Company'),
            Email::fromString($email2)
        );
        $this->tenantRepository->save($tenant2);

        $email3 = $this->generateUniqueEmail('third');
        $tenant3 = Tenant::create(
            TenantName::fromString('Third Company'),
            Email::fromString($email3)
        );
        $this->tenantRepository->save($tenant3);

        // Act
        $query = new GetAllTenantsQuery();
        $results = $this->handler->__invoke($query);

        // Assert
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(3, count($results)); // At least 3 (the ones we created)

        // Verify all items are TenantDTOs
        foreach ($results as $dto) {
            $this->assertInstanceOf(TenantDTO::class, $dto);
        }

        // Verify specific tenants are in the results
        $emails = array_map(fn (TenantDTO $dto) => $dto->ownerEmail, $results);
        $this->assertContains($email1, $emails);
        $this->assertContains($email2, $emails);
        $this->assertContains($email3, $emails);
    }

    public function testItReturnsArrayOfTenantDTOs(): void
    {
        // Arrange - Create at least one tenant to ensure we have data
        $email = $this->generateUniqueEmail('verify');
        $tenant = Tenant::create(
            TenantName::fromString('Verify Company'),
            Email::fromString($email)
        );
        $this->tenantRepository->save($tenant);

        // Act
        $query = new GetAllTenantsQuery();
        $results = $this->handler->__invoke($query);

        // Assert
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        // Verify our tenant is in the results
        $emails = array_map(fn (TenantDTO $dto) => $dto->ownerEmail, $results);
        $this->assertContains($email, $emails);
    }

    public function testReturnedDTOsHaveCorrectData(): void
    {
        // Arrange - Create tenants with specific data
        $email1 = $this->generateUniqueEmail('admin');
        $tenant1 = Tenant::create(
            TenantName::fromString('Acme Corporation'),
            Email::fromString($email1)
        );
        $this->tenantRepository->save($tenant1);

        $email2 = $this->generateUniqueEmail('contact');
        $tenant2 = Tenant::create(
            TenantName::fromString('Beta Industries'),
            Email::fromString($email2)
        );
        $this->tenantRepository->save($tenant2);

        // Act
        $query = new GetAllTenantsQuery();
        $dtos = $this->handler->__invoke($query);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($dtos)); // At least 2 (the ones we created)

        // Find and verify Acme Corporation DTO
        $acmeDto = null;
        foreach ($dtos as $dto) {
            if ($dto->ownerEmail === $email1) {
                $acmeDto = $dto;

                break;
            }
        }

        $this->assertNotNull($acmeDto);
        $this->assertSame($tenant1->id()->toString(), $acmeDto->id);
        $this->assertSame('Acme Corporation', $acmeDto->name);
        $this->assertSame($email1, $acmeDto->ownerEmail);
        $this->assertSame('active', $acmeDto->status);
        $this->assertSame($tenant1->createdAt()->format('Y-m-d H:i:s'), $acmeDto->createdAt);

        // Find and verify Beta Industries DTO
        $betaDto = null;
        foreach ($dtos as $dto) {
            if ($dto->ownerEmail === $email2) {
                $betaDto = $dto;

                break;
            }
        }

        $this->assertNotNull($betaDto);
        $this->assertSame($tenant2->id()->toString(), $betaDto->id);
        $this->assertSame('Beta Industries', $betaDto->name);
        $this->assertSame($email2, $betaDto->ownerEmail);
        $this->assertSame('active', $betaDto->status);
        $this->assertSame($tenant2->createdAt()->format('Y-m-d H:i:s'), $betaDto->createdAt);

        // Verify DTO structure for all items
        foreach ($dtos as $dto) {
            $this->assertIsString($dto->id);
            $this->assertIsString($dto->name);
            $this->assertIsString($dto->ownerEmail);
            $this->assertIsString($dto->status);
            $this->assertIsString($dto->createdAt);
        }
    }
}
