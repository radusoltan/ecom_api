<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Application\Command;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Application\Command\UpdateTenantCommand;
use App\Tenant\Application\Command\UpdateTenantCommandHandler;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTenantCommandHandler::class)]
final class UpdateTenantCommandHandlerTest extends TestCase
{
    private TenantRepositoryInterface $repository;
    private UpdateTenantCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(TenantRepositoryInterface::class);
        $this->handler = new UpdateTenantCommandHandler($this->repository);
    }

    private function makeTenant(string $name = 'Original Name', string $email = 'original@example.com'): Tenant
    {
        return Tenant::fromPersistence(
            \App\Shared\Domain\ValueObject\TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            TenantName::fromString($name),
            Email::fromString($email),
            \App\Tenant\Domain\ValueObject\TenantStatus::active(),
            new \DateTimeImmutable(),
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesTenantNameAndEmail(): void
    {
        $tenant = $this->makeTenant();
        $command = new UpdateTenantCommand(
            '00000000-0000-4000-8000-000000000001',
            'New Name',
            'new@example.com',
        );

        $this->repository
            ->method('findById')
            ->willReturn($tenant);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Tenant::class));

        ($this->handler)($command);

        self::assertSame('New Name', $tenant->name()->value());
        self::assertSame('new@example.com', $tenant->ownerEmail()->value());
    }

    #[Test]
    public function itSavesAfterUpdating(): void
    {
        $tenant = $this->makeTenant();
        $command = new UpdateTenantCommand(
            '00000000-0000-4000-8000-000000000001',
            'Updated',
            'updated@example.com',
        );

        $this->repository->method('findById')->willReturn($tenant);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Tenant not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsRuntimeExceptionWhenTenantNotFound(): void
    {
        $command = new UpdateTenantCommand(
            '00000000-0000-4000-8000-000000000002',
            'Name',
            'email@example.com',
        );

        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant not found');

        ($this->handler)($command);
    }
}
