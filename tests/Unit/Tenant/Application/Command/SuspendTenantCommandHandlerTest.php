<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Command;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\Command\SuspendTenantCommand;
use App\Tenant\Application\Command\SuspendTenantCommandHandler;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SuspendTenantCommandHandler::class)]
final class SuspendTenantCommandHandlerTest extends TestCase
{
    private TenantRepositoryInterface $repository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private SuspendTenantCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(TenantRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);

        $this->handler = new SuspendTenantCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
        );
    }

    private function makeActiveTenant(): Tenant
    {
        return Tenant::fromPersistence(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            TenantName::fromString('Active Tenant'),
            Email::fromString('active@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itSuspendsAnActiveTenant(): void
    {
        $tenant = $this->makeActiveTenant();
        $command = new SuspendTenantCommand('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::once())->method('save')->with($tenant);

        ($this->handler)($command);

        self::assertTrue($tenant->status()->isSuspended());
    }

    #[Test]
    public function itSavesAfterSuspending(): void
    {
        $tenant = $this->makeActiveTenant();
        $command = new SuspendTenantCommand('00000000-0000-4000-8000-000000000001');

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
        $command = new SuspendTenantCommand('00000000-0000-4000-8000-000000000099');

        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(TenantNotFoundException::class);

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Domain rule violation propagation
    // -----------------------------------------------------------------------

    #[Test]
    public function itRethrowsDomainExceptionWhenAlreadySuspended(): void
    {
        $tenant = Tenant::fromPersistence(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            TenantName::fromString('Suspended Tenant'),
            Email::fromString('suspended@example.com'),
            TenantStatus::suspended(),
            new \DateTimeImmutable(),
        );

        $command = new SuspendTenantCommand('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tenant is already suspended');

        ($this->handler)($command);
    }
}
