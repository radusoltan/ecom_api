<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Presentation\Api\Processor;

use App\Tenant\Application\Command\ActivateTenantCommand;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Application\Query\GetTenantByIdQuery;
use App\Tenant\Presentation\Api\Processor\ActivateTenantProcessor;
use App\Tenant\Presentation\Api\TenantResource;
use ApiPlatform\Metadata\Patch;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class ActivateTenantProcessorTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private ActivateTenantProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new ActivateTenantProcessor($this->commandBus, $this->queryBus);
    }

    public function testProcessActivatesTenantSuccessfully(): void
    {
        $tenantId = '123e4567-e89b-12d3-a456-426614174000';
        $resource = new TenantResource();

        $tenantDTO = new TenantDTO(
            id: $tenantId,
            name: 'Test Tenant',
            ownerEmail: 'owner@example.com',
            status: 'active',
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            defaultLocale: 'en',
            enabledLocales: ['en']
        );

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($tenantId) {
                return $command instanceof ActivateTenantCommand
                    && $command->tenantId === $tenantId;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $envelope = new Envelope(
            new GetTenantByIdQuery($tenantId),
            [new HandledStamp($tenantDTO, 'handler')]
        );

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($query) use ($tenantId) {
                return $query instanceof GetTenantByIdQuery
                    && $query->tenantId === $tenantId;
            }))
            ->willReturn($envelope);

        $result = $this->processor->process($resource, new Patch(), ['id' => $tenantId]);

        $this->assertInstanceOf(TenantResource::class, $result);
        $this->assertSame($tenantId, $result->id);
        $this->assertSame('active', $result->status);
    }

    public function testProcessThrowsExceptionWhenTenantIdIsMissing(): void
    {
        $resource = new TenantResource();

        // No mock expectations - exception should be thrown before any dispatch

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $this->processor->process($resource, new Patch(), []);
    }

    public function testProcessThrowsExceptionWhenTenantIdIsNull(): void
    {
        $resource = new TenantResource();

        // No mock expectations - exception should be thrown before any dispatch

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $this->processor->process($resource, new Patch(), ['id' => null]);
    }

    public function testProcessThrowsExceptionWhenNoHandlerStampFound(): void
    {
        $tenantId = '123e4567-e89b-12d3-a456-426614174000';
        $resource = new TenantResource();

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Envelope without HandledStamp
        $envelope = new Envelope(new GetTenantByIdQuery($tenantId));

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $this->processor->process($resource, new Patch(), ['id' => $tenantId]);
    }

    public function testProcessThrowsExceptionWhenTenantNotFoundAfterActivation(): void
    {
        $tenantId = '123e4567-e89b-12d3-a456-426614174000';
        $resource = new TenantResource();

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // HandledStamp with null result
        $envelope = new Envelope(
            new GetTenantByIdQuery($tenantId),
            [new HandledStamp(null, 'handler')]
        );

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant not found after activation');

        $this->processor->process($resource, new Patch(), ['id' => $tenantId]);
    }

    public function testProcessWithInvalidUuidFormat(): void
    {
        $resource = new TenantResource();
        $invalidId = 'not-a-uuid';

        // The processor passes the ID to the command/query
        // Validation happens in the domain layer
        $tenantDTO = new TenantDTO(
            id: $invalidId,
            name: 'Test Tenant',
            ownerEmail: 'owner@example.com',
            status: 'active',
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            defaultLocale: 'en',
            enabledLocales: ['en']
        );

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $envelope = new Envelope(
            new GetTenantByIdQuery($invalidId),
            [new HandledStamp($tenantDTO, 'handler')]
        );

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $result = $this->processor->process($resource, new Patch(), ['id' => $invalidId]);

        $this->assertInstanceOf(TenantResource::class, $result);
    }
}
