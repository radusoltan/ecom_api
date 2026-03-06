<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Application\Query;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Application\Query\GetAllTenantsQuery;
use App\Tenant\Application\Query\GetAllTenantsQueryHandler;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetAllTenantsQueryHandlerTest extends KernelTestCase
{
    use TenantTestTrait;

    private TenantRepositoryInterface $tenantRepository;
    private GetAllTenantsQueryHandler $handler;
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
        $this->handler = $container->get(GetAllTenantsQueryHandler::class);

        // Use a fresh unique tenant ID per test to avoid ext_translations unique constraint violations
        $this->tenantId = TenantId::generate();
        $this->setTenantContext($this->tenantId->toString());
    }

    protected function tearDown(): void
    {
        // Clean up test tenants to avoid slug unique constraint violations
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $conn = $em->getConnection();
        $conn->executeStatement('DELETE FROM ext_translations WHERE foreign_key = ?', [$this->tenantId->toString()]);
        $conn->executeStatement('DELETE FROM tenants WHERE id = ?', [$this->tenantId->toString()]);
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

    public function testItRetrievesAllTenants(): void
    {
        // Arrange - Use the default test tenant (required for RLS)
        // Note: We can only test with one tenant due to RLS constraints
        $email1 = $this->generateUniqueEmail('first');
        $tenant1 = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($this->uniqueName('First Company')),
            ownerEmail: Email::fromString($email1),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: new \DateTimeImmutable()
        );
        $this->tenantRepository->save($tenant1);

        // Act
        $query = new GetAllTenantsQuery();
        $results = $this->handler->__invoke($query);

        // Assert
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results)); // At least 1 (the one we created)

        // Verify all items are TenantDTOs
        foreach ($results as $dto) {
            $this->assertInstanceOf(TenantDTO::class, $dto);
        }

        // Verify our tenant is in the results
        $emails = array_map(fn (TenantDTO $dto) => $dto->ownerEmail, $results);
        $this->assertContains($email1, $emails);
    }

    public function testItReturnsArrayOfTenantDTOs(): void
    {
        // Arrange - Use the default test tenant (required for RLS)
        $email = $this->generateUniqueEmail('verify');
        $tenant = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($this->uniqueName('Verify Company')),
            ownerEmail: Email::fromString($email),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: new \DateTimeImmutable()
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
        // Arrange - Use the default test tenant (required for RLS)
        $email1 = $this->generateUniqueEmail('admin');
        $createdAt = new \DateTimeImmutable();
        $name1 = $this->uniqueName('Acme Corporation');
        $tenant1 = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($name1),
            ownerEmail: Email::fromString($email1),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: $createdAt
        );
        $this->tenantRepository->save($tenant1);

        // Act
        $query = new GetAllTenantsQuery();
        $dtos = $this->handler->__invoke($query);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($dtos)); // At least 1 (the one we created)

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
        $this->assertSame($name1, $acmeDto->name);
        $this->assertSame($email1, $acmeDto->ownerEmail);
        $this->assertSame('active', $acmeDto->status);

        // Compare timestamps with tolerance (database microseconds may differ)
        $expectedTimestamp = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $acmeDto->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $expectedTimestamp);
        $this->assertEqualsWithDelta($createdAt->getTimestamp(), $expectedTimestamp->getTimestamp(), 10);

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
