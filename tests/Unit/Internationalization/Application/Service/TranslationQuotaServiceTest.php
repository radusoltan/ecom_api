<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Application\Service;

use App\Internationalization\Application\Service\TranslationQuotaService;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Exception\TranslationQuotaExceededException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantStatus;
use PHPUnit\Framework\TestCase;

final class TranslationQuotaServiceTest extends TestCase
{
    private TenantRepositoryInterface $tenantRepository;
    private TranslationQuotaService $service;

    protected function setUp(): void
    {
        $this->tenantRepository = $this->createMock(TenantRepositoryInterface::class);
        $this->service = new TranslationQuotaService($this->tenantRepository);
    }

    public function testCheckQuotaReturnsTrueWhenQuotaAvailable(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            5000   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        // Act
        $result = $this->service->checkQuota($tenantId, 100);

        // Assert
        $this->assertTrue($result);
    }

    public function testCheckQuotaReturnsFalseWhenQuotaExceeded(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            9900   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        // Act
        $result = $this->service->checkQuota($tenantId, 200);

        // Assert
        $this->assertFalse($result);
    }

    public function testIncrementUsageIncrementsSuccessfully(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            5000   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        $this->tenantRepository
            ->expects($this->once())
            ->method('save')
            ->with($tenant);

        // Act
        $this->service->incrementUsage($tenantId, 10);

        // Assert
        $this->assertEquals(5010, $tenant->translationUsage());
    }

    public function testIncrementUsageThrowsExceptionWhenQuotaExceeded(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            9990   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        // Assert
        $this->expectException(TranslationQuotaExceededException::class);

        // Act
        $this->service->incrementUsage($tenantId, 20);
    }

    public function testGetUsageReturnsCorrectValue(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            3500   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        // Act
        $usage = $this->service->getUsage($tenantId);

        // Assert
        $this->assertEquals(3500, $usage);
    }

    public function testGetRemainingQuotaReturnsCorrectValue(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            3500   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        // Act
        $remaining = $this->service->getRemainingQuota($tenantId);

        // Assert
        $this->assertEquals(6500, $remaining);
    }

    public function testGetQuotaReturnsCorrectValue(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            50000, // quota
            3500   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        // Act
        $quota = $this->service->getQuota($tenantId);

        // Assert
        $this->assertEquals(50000, $quota);
    }

    public function testResetUsageResetsSuccessfully(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            7500   // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        $this->tenantRepository
            ->expects($this->once())
            ->method('save')
            ->with($tenant);

        // Act
        $this->service->resetUsage($tenantId);

        // Assert
        $this->assertEquals(0, $tenant->translationUsage());
    }

    public function testSetQuotaSetsSuccessfully(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $tenant = Tenant::fromPersistence(
            $tenantId,
            TenantName::fromString('Test Tenant'),
            Email::fromString('test@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
            null,
            null,
            10000, // quota
            0      // usage
        );

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn($tenant);

        $this->tenantRepository
            ->expects($this->once())
            ->method('save')
            ->with($tenant);

        // Act
        $this->service->setQuota($tenantId, 100000);

        // Assert
        $this->assertEquals(100000, $tenant->translationQuota());
    }

    public function testCheckQuotaThrowsExceptionWhenTenantNotFound(): void
    {
        // Arrange
        $tenantId = TenantId::generate();

        $this->tenantRepository
            ->expects($this->once())
            ->method('findById')
            ->with($tenantId)
            ->willReturn(null);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tenant not found');

        // Act
        $this->service->checkQuota($tenantId);
    }
}
