<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Application\Query;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Application\Query\GetTenantByOwnerEmailQuery;
use App\Tenant\Application\Query\GetTenantByOwnerEmailQueryHandler;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetTenantByOwnerEmailQueryHandlerTest extends KernelTestCase
{
    use TenantTestTrait;

    private TenantRepositoryInterface $tenantRepository;
    private GetTenantByOwnerEmailQueryHandler $handler;
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
        $this->handler = $container->get(GetTenantByOwnerEmailQueryHandler::class);

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

    public function testItRetrievesTenantByOwnerEmail(): void
    {
        // Arrange - Use the default test tenant (required for RLS)
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

        $tenantId = $tenant->id()->toString();

        // Act
        $query = new GetTenantByOwnerEmailQuery($email);
        $result = $this->handler->__invoke($query);

        // Assert
        $this->assertInstanceOf(TenantDTO::class, $result);
        $this->assertSame($tenantId, $result->id);
        $this->assertSame($name, $result->name);
        $this->assertSame($email, $result->ownerEmail);
        $this->assertSame('active', $result->status);
        $this->assertNotEmpty($result->createdAt);
    }

    public function testItThrowsExceptionWhenTenantDoesNotExist(): void
    {
        // Arrange
        $nonExistentEmail = $this->generateUniqueEmail('nonexistent');
        $query = new GetTenantByOwnerEmailQuery($nonExistentEmail);

        // Assert & Act
        $this->expectException(TenantNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Tenant with owner email "%s" not found', $nonExistentEmail));

        $this->handler->__invoke($query);
    }

    public function testReturnedDTOHasCorrectData(): void
    {
        // Arrange - Use the default test tenant (required for RLS)
        $email = $this->generateUniqueEmail('admin');
        $createdAt = new \DateTimeImmutable();
        $name = $this->uniqueName('Acme Corporation');
        $tenant = Tenant::fromPersistence(
            id: $this->tenantId,
            name: TenantName::fromString($name),
            ownerEmail: Email::fromString($email),
            status: \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            createdAt: $createdAt
        );
        $this->tenantRepository->save($tenant);

        $tenantId = $tenant->id()->toString();

        // Act
        $query = new GetTenantByOwnerEmailQuery($email);
        $dto = $this->handler->__invoke($query);

        // Assert - Verify all DTO properties match the tenant
        $this->assertSame($tenantId, $dto->id);
        $this->assertSame($name, $dto->name);
        $this->assertSame($email, $dto->ownerEmail);
        $this->assertSame('active', $dto->status);

        // Compare timestamps with tolerance (database microseconds may differ)
        $expectedTimestamp = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dto->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $expectedTimestamp);
        $this->assertEqualsWithDelta($createdAt->getTimestamp(), $expectedTimestamp->getTimestamp(), 10);

        // Verify DTO structure
        $this->assertIsString($dto->id);
        $this->assertIsString($dto->name);
        $this->assertIsString($dto->ownerEmail);
        $this->assertIsString($dto->status);
        $this->assertIsString($dto->createdAt);
    }
}
