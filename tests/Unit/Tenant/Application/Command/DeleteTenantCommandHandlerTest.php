<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Application\Command\DeleteTenantCommand;
use App\Tenant\Application\Command\DeleteTenantCommandHandler;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteTenantCommandHandler::class)]
final class DeleteTenantCommandHandlerTest extends TestCase
{
    private TenantRepositoryInterface $repository;
    private DeleteTenantCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(TenantRepositoryInterface::class);
        $this->handler = new DeleteTenantCommandHandler($this->repository);
    }

    private function makeTenant(): Tenant
    {
        return Tenant::fromPersistence(
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            TenantName::fromString('To Delete'),
            Email::fromString('delete@example.com'),
            TenantStatus::active(),
            new \DateTimeImmutable(),
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itDeletesTenantWhenFound(): void
    {
        $tenant = $this->makeTenant();
        $command = new DeleteTenantCommand('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findById')->willReturn($tenant);

        $this->repository
            ->expects(self::once())
            ->method('delete')
            ->with($tenant);

        ($this->handler)($command);
    }

    #[Test]
    public function itDoesNotCallSaveOnDelete(): void
    {
        $tenant = $this->makeTenant();
        $command = new DeleteTenantCommand('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::once())->method('delete');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Tenant not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsRuntimeExceptionWhenTenantNotFound(): void
    {
        $command = new DeleteTenantCommand('00000000-0000-4000-8000-000000000099');

        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('delete');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant not found');

        ($this->handler)($command);
    }
}
