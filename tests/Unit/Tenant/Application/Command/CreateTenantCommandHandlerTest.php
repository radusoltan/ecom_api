<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Command;

use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Application\Service\TenantContextInterface;
use App\Tenant\Application\Command\CreateTenantCommand;
use App\Tenant\Application\Command\CreateTenantCommandHandler;
use App\Tenant\Domain\Exception\TenantAlreadyExistsException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CreateTenantCommandHandler::class)]
final class CreateTenantCommandHandlerTest extends TestCase
{
    private TenantRepositoryInterface $repository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private TenantContextInterface $tenantContext;
    private CreateTenantCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(TenantRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        // PerformanceProfiler is a concrete non-final class — instantiate it directly
        $this->profiler = new PerformanceProfiler($this->logger);

        $this->handler = new CreateTenantCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger,
            $this->tenantContext,
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesTenantWhenNoConflictExists(): void
    {
        $command = new CreateTenantCommand('Acme Corp', 'owner@acme.com');

        $this->repository
            ->expects(self::once())
            ->method('findByOwnerEmail')
            ->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Tenant::class));

        $this->tenantContext
            ->expects(self::once())
            ->method('setCurrentTenant');

        ($this->handler)($command);
    }

    #[Test]
    public function itPersistsTenantWithCorrectName(): void
    {
        $command = new CreateTenantCommand('My Store', 'store@example.com');

        $this->repository->method('findByOwnerEmail')->willReturn(null);

        $savedTenant = null;
        $this->repository
            ->method('save')
            ->willReturnCallback(function (Tenant $tenant) use (&$savedTenant): void {
                $savedTenant = $tenant;
            });

        ($this->handler)($command);

        self::assertNotNull($savedTenant);
        self::assertSame('My Store', $savedTenant->name()->value());
    }

    #[Test]
    public function itPersistsTenantWithCorrectOwnerEmail(): void
    {
        $command = new CreateTenantCommand('My Store', 'store@example.com');

        $this->repository->method('findByOwnerEmail')->willReturn(null);

        $savedTenant = null;
        $this->repository
            ->method('save')
            ->willReturnCallback(function (Tenant $tenant) use (&$savedTenant): void {
                $savedTenant = $tenant;
            });

        ($this->handler)($command);

        self::assertSame('store@example.com', $savedTenant->ownerEmail()->value());
    }

    #[Test]
    public function itSetsTenantContextBeforeSaving(): void
    {
        $command = new CreateTenantCommand('Context Corp', 'ctx@example.com');

        $this->repository->method('findByOwnerEmail')->willReturn(null);
        $this->repository->method('save');

        $callOrder = [];

        $this->tenantContext
            ->expects(self::once())
            ->method('setCurrentTenant')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'context';
            });

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'save';
            });

        ($this->handler)($command);

        self::assertSame(['context', 'save'], $callOrder);
    }

    // -----------------------------------------------------------------------
    // Duplicate email guard
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsTenantAlreadyExistsWhenEmailConflict(): void
    {
        $command = new CreateTenantCommand('Duplicate Corp', 'dup@example.com');

        $existingTenant = Tenant::create(
            TenantName::fromString('Existing Corp'),
            \App\Shared\Domain\ValueObject\Email::fromString('dup@example.com'),
        );

        $this->repository
            ->method('findByOwnerEmail')
            ->willReturn($existingTenant);

        $this->repository->expects(self::never())->method('save');
        $this->tenantContext->expects(self::never())->method('setCurrentTenant');

        $this->expectException(TenantAlreadyExistsException::class);

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Propagation of repository errors
    // -----------------------------------------------------------------------

    #[Test]
    public function itRethrowsRepositoryExceptionOnSaveFailure(): void
    {
        $command = new CreateTenantCommand('Error Corp', 'error@example.com');

        $this->repository->method('findByOwnerEmail')->willReturn(null);
        $this->tenantContext->method('setCurrentTenant');

        $this->repository
            ->method('save')
            ->willThrowException(new \RuntimeException('DB connection lost'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB connection lost');

        ($this->handler)($command);
    }
}
