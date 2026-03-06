<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Delete;
use App\Customer\Presentation\Api\Processor\RemoveLoyaltyTierProcessor;
use App\Customer\Presentation\Api\Resource\LoyaltyTierResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final class RemoveLoyaltyTierProcessorTest extends TestCase
{
    private MessageBusInterface&MockObject $commandBus;
    private TenantContextInterface&MockObject $tenantContext;
    private RemoveLoyaltyTierProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->processor = new RemoveLoyaltyTierProcessor($this->commandBus, $this->tenantContext);
    }

    public function testProcessSuccessfully(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->tenantContext->method('getCurrentTenantId')->willReturn($tenantId);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // void return, no exception means success
        $this->processor->process(
            new LoyaltyTierResource(),
            new Delete(),
            ['programId' => '00000000-0000-4000-8000-000000000010', 'id' => '00000000-0000-4000-8000-000000000020'],
        );

        $this->addToAssertionCount(1);
    }

    public function testThrowsWhenProgramIdOrTierIdMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Program ID and tier ID are required');

        $this->processor->process(
            new LoyaltyTierResource(),
            new Delete(),
            ['programId' => '00000000-0000-4000-8000-000000000010'],
        );
    }

    public function testThrowsWhenBothUriVariablesMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Program ID and tier ID are required');

        $this->processor->process(
            new LoyaltyTierResource(),
            new Delete(),
            [],
        );
    }

    public function testThrowsWhenTenantContextMissing(): void
    {
        $this->tenantContext->method('getCurrentTenantId')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $this->processor->process(
            new LoyaltyTierResource(),
            new Delete(),
            ['programId' => '00000000-0000-4000-8000-000000000010', 'id' => '00000000-0000-4000-8000-000000000020'],
        );
    }

    public function testRethrowsHttpException(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->tenantContext->method('getCurrentTenantId')->willReturn($tenantId);

        $httpException = new NotFoundHttpException('Tier not found');
        $handlerFailed = new HandlerFailedException(
            new Envelope(new \stdClass()),
            [$httpException],
        );

        $this->commandBus->method('dispatch')
            ->willThrowException($handlerFailed);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Tier not found');

        $this->processor->process(
            new LoyaltyTierResource(),
            new Delete(),
            ['programId' => '00000000-0000-4000-8000-000000000010', 'id' => '00000000-0000-4000-8000-000000000020'],
        );
    }

    public function testRethrowsHandlerFailedExceptionForNonHttpException(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->tenantContext->method('getCurrentTenantId')->willReturn($tenantId);

        $genericException = new \RuntimeException('Something went wrong');
        $handlerFailed = new HandlerFailedException(
            new Envelope(new \stdClass()),
            [$genericException],
        );

        $this->commandBus->method('dispatch')
            ->willThrowException($handlerFailed);

        $this->expectException(HandlerFailedException::class);

        $this->processor->process(
            new LoyaltyTierResource(),
            new Delete(),
            ['programId' => '00000000-0000-4000-8000-000000000010', 'id' => '00000000-0000-4000-8000-000000000020'],
        );
    }
}
