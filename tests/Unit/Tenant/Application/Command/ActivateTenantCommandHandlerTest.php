<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Command;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\Command\ActivateTenantCommand;
use App\Tenant\Application\Command\ActivateTenantCommandHandler;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ActivateTenantCommandHandler::class)]
final class ActivateTenantCommandHandlerTest extends TestCase
{
    private TenantRepositoryInterface $repository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private ActivateTenantCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(TenantRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);

        $this->handler = new ActivateTenantCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );
    }

    private function makeInactiveTenant(): Tenant
    {
        return Tenant::fromPersistence(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            TenantName::fromString('Inactive Tenant'),
            Email::fromString('inactive@example.com'),
            TenantStatus::inactive(),
            new \DateTimeImmutable(),
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itActivatesAnInactiveTenant(): void
    {
        $tenant = $this->makeInactiveTenant();
        $command = new ActivateTenantCommand('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::once())->method('save')->with($tenant);

        ($this->handler)($command);

        self::assertTrue($tenant->status()->isActive());
    }

    #[Test]
    public function itSavesAfterActivating(): void
    {
        $tenant = $this->makeInactiveTenant();
        $command = new ActivateTenantCommand('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Tenant not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsTenantNotFoundExceptionWhenMissing(): void
    {
        $command = new ActivateTenantCommand('00000000-0000-4000-8000-000000000099');

        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(TenantNotFoundException::class);

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Domain rule violation propagation
    // -----------------------------------------------------------------------

    #[Test]
    public function itRethrowsDomainExceptionWhenAlreadyActive(): void
    {
        $tenant = Tenant::fromPersistence(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            TenantName::fromString('Active Tenant'),
            Email::fromString('active@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
        );

        $command = new ActivateTenantCommand('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tenant is already active');

        ($this->handler)($command);
    }
}
