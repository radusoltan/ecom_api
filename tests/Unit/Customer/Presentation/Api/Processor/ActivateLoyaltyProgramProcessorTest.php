<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\ActivateLoyaltyProgram\ActivateLoyaltyProgramCommand;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\DTO\LoyaltyTierDTO;
use App\Customer\Application\Query\GetLoyaltyProgramById\GetLoyaltyProgramByIdQuery;
use App\Customer\Presentation\Api\Processor\ActivateLoyaltyProgramProcessor;
use App\Customer\Presentation\Api\Resource\LoyaltyProgramResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ActivateLoyaltyProgramProcessor::class)]
final class ActivateLoyaltyProgramProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PROGRAM_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private ActivateLoyaltyProgramProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new ActivateLoyaltyProgramProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesActivateCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildLoyaltyProgramDTO(isActive: true);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ActivateLoyaltyProgramCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetLoyaltyProgramByIdQuery::class))
            ->willReturn($queryEnvelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process(
            null,
            $operation,
            ['id' => self::PROGRAM_ID],
            []
        );

        self::assertInstanceOf(LoyaltyProgramResource::class, $result);
        self::assertSame($dto->id, $result->id);
        self::assertSame($dto->tenantId, $result->tenantId);
        self::assertSame($dto->name, $result->name);
        self::assertTrue($result->isActive);
    }

    #[Test]
    public function itMapsTiersFromDtoToResource(): void
    {
        $tier = new LoyaltyTierDTO(
            id: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            name: 'Gold',
            threshold: 500,
            discountPercentage: 10,
            freeShippingMinOrder: ['amount' => 5000, 'currency' => 'USD'],
            sortOrder: 1,
        );
        $dto = $this->buildLoyaltyProgramDTO(isActive: true, tiers: [$tier]);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);

        self::assertCount(1, $result->tiers);
        self::assertSame('Gold', $result->tiers[0]['name']);
        self::assertSame(500, $result->tiers[0]['threshold']);
        self::assertSame(10, $result->tiers[0]['discountPercentage']);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenProgramIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Program ID is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new ActivateLoyaltyProgramProcessor(
            $this->commandBus,
            $this->queryBus,
            $tenantContext,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itRethrowsHttpExceptionFromHandlerFailedException(): void
    {
        $httpException = new BadRequestHttpException('Program already active');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$httpException]);

        $this->commandBus
            ->method('dispatch')
            ->willThrowException($handlerFailed);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Program already active');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itRethrowsHandlerFailedExceptionWhenPreviousIsNotHttpException(): void
    {
        $cause = new \RuntimeException('DB error');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$cause]);

        $this->commandBus
            ->method('dispatch')
            ->willThrowException($handlerFailed);

        $this->expectException(HandlerFailedException::class);

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Loyalty program not found after activation');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @param array<LoyaltyTierDTO> $tiers
     */
    private function buildLoyaltyProgramDTO(bool $isActive = true, array $tiers = []): LoyaltyProgramDTO
    {
        return new LoyaltyProgramDTO(
            id: self::PROGRAM_ID,
            tenantId: self::TENANT_ID,
            name: 'Test Program',
            description: 'Test Description',
            earningRate: 1.5,
            minOrderValue: ['amount' => 1000, 'currency' => 'USD'],
            redemptionRule: ['rate' => 100, 'currency' => 'USD'],
            validityDays: 365,
            isActive: $isActive,
            tiers: $tiers,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );
    }
}
