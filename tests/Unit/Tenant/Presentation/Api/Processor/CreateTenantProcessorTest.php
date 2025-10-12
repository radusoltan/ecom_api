<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Presentation\Api\Processor;

use App\Tenant\Application\Command\CreateTenantCommand;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Application\Query\GetTenantByOwnerEmailQuery;
use App\Tenant\Presentation\Api\Processor\CreateTenantProcessor;
use App\Tenant\Presentation\Api\TenantResource;
use ApiPlatform\Metadata\Post;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CreateTenantProcessorTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private CreateTenantProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new CreateTenantProcessor($this->commandBus, $this->queryBus);
    }

    public function testProcessCreatesTenantSuccessfully(): void
    {
        $resource = new TenantResource();
        $resource->name = 'Test Tenant';
        $resource->ownerEmail = 'owner@example.com';

        $tenantDTO = new TenantDTO(
            id: '123e4567-e89b-12d3-a456-426614174000',
            name: 'Test Tenant',
            ownerEmail: 'owner@example.com',
            status: 'active',
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) {
                return $command instanceof CreateTenantCommand
                    && $command->name === 'Test Tenant'
                    && $command->ownerEmail === 'owner@example.com';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $envelope = new Envelope(
            new GetTenantByOwnerEmailQuery('owner@example.com'),
            [new HandledStamp($tenantDTO, 'handler')]
        );

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($query) {
                return $query instanceof GetTenantByOwnerEmailQuery
                    && $query->ownerEmail === 'owner@example.com';
            }))
            ->willReturn($envelope);

        $result = $this->processor->process($resource, new Post());

        $this->assertInstanceOf(TenantResource::class, $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->id);
        $this->assertSame('Test Tenant', $result->name);
        $this->assertSame('owner@example.com', $result->ownerEmail);
        $this->assertSame('active', $result->status);
    }

    public function testProcessThrowsExceptionWhenDataIsNotTenantResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected TenantResource');

        $this->processor->process(new \stdClass(), new Post());
    }

    public function testProcessThrowsExceptionWhenNameIsNull(): void
    {
        $resource = new TenantResource();
        $resource->name = null;
        $resource->ownerEmail = 'owner@example.com';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name and ownerEmail are required');

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsExceptionWhenOwnerEmailIsNull(): void
    {
        $resource = new TenantResource();
        $resource->name = 'Test Tenant';
        $resource->ownerEmail = null;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name and ownerEmail are required');

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsExceptionWhenBothNameAndEmailAreNull(): void
    {
        $resource = new TenantResource();
        $resource->name = null;
        $resource->ownerEmail = null;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name and ownerEmail are required');

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsExceptionWhenNoHandlerStampFound(): void
    {
        $resource = new TenantResource();
        $resource->name = 'Test Tenant';
        $resource->ownerEmail = 'owner@example.com';

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Envelope without HandledStamp
        $envelope = new Envelope(new GetTenantByOwnerEmailQuery('owner@example.com'));

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsExceptionWhenTenantNotFoundAfterCreation(): void
    {
        $resource = new TenantResource();
        $resource->name = 'Test Tenant';
        $resource->ownerEmail = 'owner@example.com';

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // HandledStamp with null result
        $envelope = new Envelope(
            new GetTenantByOwnerEmailQuery('owner@example.com'),
            [new HandledStamp(null, 'handler')]
        );

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant not found after creation');

        $this->processor->process($resource, new Post());
    }

    public function testProcessWithEmptyStringName(): void
    {
        $resource = new TenantResource();
        $resource->name = '';
        $resource->ownerEmail = 'owner@example.com';

        // Empty string is still considered as provided (not null)
        // The validation should happen in the domain/handler
        $tenantDTO = new TenantDTO(
            id: '123e4567-e89b-12d3-a456-426614174000',
            name: '',
            ownerEmail: 'owner@example.com',
            status: 'active',
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $envelope = new Envelope(
            new GetTenantByOwnerEmailQuery('owner@example.com'),
            [new HandledStamp($tenantDTO, 'handler')]
        );

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $result = $this->processor->process($resource, new Post());

        $this->assertInstanceOf(TenantResource::class, $result);
    }

    public function testProcessWithWhitespaceOnlyName(): void
    {
        $resource = new TenantResource();
        $resource->name = '   ';
        $resource->ownerEmail = 'owner@example.com';

        $tenantDTO = new TenantDTO(
            id: '123e4567-e89b-12d3-a456-426614174000',
            name: '   ',
            ownerEmail: 'owner@example.com',
            status: 'active',
            createdAt: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $envelope = new Envelope(
            new GetTenantByOwnerEmailQuery('owner@example.com'),
            [new HandledStamp($tenantDTO, 'handler')]
        );

        $this->queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $result = $this->processor->process($resource, new Post());

        $this->assertInstanceOf(TenantResource::class, $result);
    }
}
