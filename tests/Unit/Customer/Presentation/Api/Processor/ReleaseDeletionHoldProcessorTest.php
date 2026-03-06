<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Patch;
use App\Customer\Presentation\Api\Processor\ReleaseDeletionHoldProcessor;
use App\Customer\Presentation\Api\Resource\DeletionRequestResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReleaseDeletionHoldProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private TenantContextInterface&MockObject $tenantContext;
    private ReleaseDeletionHoldProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->processor = new ReleaseDeletionHoldProcessor($this->commandBus, $this->tenantContext);
    }

    public function testProcessSuccessfully(): void
    {
        $requestId = '00000000-0000-4000-8000-000000000010';
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->tenantContext->method('getCurrentTenantId')->willReturn($tenantId);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->processor->process(
            new DeletionRequestResource(),
            new Patch(),
            ['requestId' => $requestId],
        );

        $this->assertInstanceOf(DeletionRequestResource::class, $result);
        $this->assertSame($requestId, $result->id);
        $this->assertSame('confirmed', $result->status);
        $this->assertSame('Confirmed - Scheduled for Deletion', $result->statusLabel);
        $this->assertNull($result->holdReason);
        $this->assertFalse($result->isOnHold);
        $this->assertSame('Deletion request released from hold', $result->message);
    }

    public function testThrowsWhenRequestIdMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request ID is required');

        $this->processor->process(
            new DeletionRequestResource(),
            new Patch(),
            [],
        );
    }

    public function testThrowsWhenTenantContextMissing(): void
    {
        $this->tenantContext->method('getCurrentTenantId')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $this->processor->process(
            new DeletionRequestResource(),
            new Patch(),
            ['requestId' => '00000000-0000-4000-8000-000000000010'],
        );
    }
}
