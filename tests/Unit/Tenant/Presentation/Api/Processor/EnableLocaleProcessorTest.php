<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Presentation\Api\Processor\EnableLocaleProcessor;
use App\Tenant\Presentation\Api\TenantResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class EnableLocaleProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private MessageBusInterface&MockObject $queryBus;
    private EnableLocaleProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new EnableLocaleProcessor($this->commandBus, $this->queryBus);
    }

    public function testProcessSuccessfully(): void
    {
        $data = new TenantResource();
        $data->localeCode = 'de';

        $tenantDto = new TenantDTO(
            id: '00000000-0000-4000-8000-000000000001',
            name: 'Test Tenant',
            ownerEmail: 'owner@test.com',
            status: 'active',
            createdAt: '2026-01-01 00:00:00',
            defaultLocale: 'en',
            enabledLocales: ['en', 'de'],
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
            new Post(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );

        $this->assertInstanceOf(TenantResource::class, $result);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $result->id);
        $this->assertSame(['en', 'de'], $result->enabledLocales);
    }

    public function testThrowsWhenTenantIdMissing(): void
    {
        $data = new TenantResource();
        $data->localeCode = 'de';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $this->processor->process($data, new Post(), []);
    }

    public function testThrowsWhenLocaleCodeIsNull(): void
    {
        $data = new TenantResource();
        $data->localeCode = null;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale code is required');

        $this->processor->process(
            $data,
            new Post(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );
    }

    public function testThrowsWhenNoHandlerFoundForQuery(): void
    {
        $data = new TenantResource();
        $data->localeCode = 'de';

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->queryBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $this->processor->process(
            $data,
            new Post(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );
    }

    public function testThrowsWhenTenantNotFoundAfterUpdate(): void
    {
        $data = new TenantResource();
        $data->localeCode = 'de';

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(
            new \stdClass(),
            [new HandledStamp(null, 'handler')],
        );
        $this->queryBus->method('dispatch')
            ->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant not found after locale enable');

        $this->processor->process(
            $data,
            new Post(),
            ['id' => '00000000-0000-4000-8000-000000000001'],
        );
    }
}
