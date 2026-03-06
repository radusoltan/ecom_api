<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Patch;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Presentation\Api\Processor\SetDefaultLocaleProcessor;
use App\Tenant\Presentation\Api\TenantResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class SetDefaultLocaleProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private MessageBusInterface&MockObject $queryBus;
    private SetDefaultLocaleProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new SetDefaultLocaleProcessor($this->commandBus, $this->queryBus);
    }

    public function testProcessSuccessfully(): void
    {
        $data = new TenantResource();
        $data->defaultLocale = 'fr';

        $tenantDto = new TenantDTO(
            id: '00000000-0000-4000-8000-000000000001',
            name: 'Test Tenant',
            ownerEmail: 'owner@test.com',
            status: 'active',
            createdAt: '2026-01-01 00:00:00',
            defaultLocale: 'fr',
            enabledLocales: ['en', 'fr'],
        );

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(
            new \stdClass(),
            [new HandledStamp($tenantDto, 'handler')],
        );
        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $result = $this->processor->process(
            $data,
            new Patch(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );

        $this->assertInstanceOf(TenantResource::class, $result);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $result->id);
        $this->assertSame('fr', $result->defaultLocale);
        $this->assertSame('Test Tenant', $result->name);
    }

    public function testThrowsWhenTenantIdMissing(): void
    {
        $data = new TenantResource();
        $data->defaultLocale = 'fr';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $this->processor->process($data, new Patch(), []);
    }

    public function testThrowsWhenDefaultLocaleIsNull(): void
    {
        $data = new TenantResource();
        $data->defaultLocale = null;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default locale is required');

        $this->processor->process(
            $data,
            new Patch(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );
    }

    public function testThrowsWhenNoHandlerFoundForQuery(): void
    {
        $data = new TenantResource();
        $data->defaultLocale = 'fr';

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Return envelope without HandledStamp
        $this->queryBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $this->processor->process(
            $data,
            new Patch(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );
    }

    public function testThrowsWhenTenantNotFoundAfterUpdate(): void
    {
        $data = new TenantResource();
        $data->defaultLocale = 'fr';

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(
            new \stdClass(),
            [new HandledStamp(null, 'handler')],
        );
        $this->queryBus->method('dispatch')
            ->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant not found after locale update');

        $this->processor->process(
            $data,
            new Patch(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );
    }
}
