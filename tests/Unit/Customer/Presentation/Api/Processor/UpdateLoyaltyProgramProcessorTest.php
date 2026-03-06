<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\UpdateLoyaltyProgram\UpdateLoyaltyProgramCommand;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\DTO\LoyaltyTierDTO;
use App\Customer\Application\Query\GetLoyaltyProgramById\GetLoyaltyProgramByIdQuery;
use App\Customer\Presentation\Api\Processor\UpdateLoyaltyProgramProcessor;
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

#[CoversClass(UpdateLoyaltyProgramProcessor::class)]
final class UpdateLoyaltyProgramProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PROGRAM_ID = '11111111-1111-4111-8111-111111111111';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private UpdateLoyaltyProgramProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new UpdateLoyaltyProgramProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesUpdateCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildLoyaltyProgramDTO();

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdateLoyaltyProgramCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetLoyaltyProgramByIdQuery::class))
            ->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['id' => self::PROGRAM_ID],
            []
        );

        self::assertInstanceOf(LoyaltyProgramResource::class, $result);
        self::assertSame($dto->id, $result->id);
        self::assertSame($dto->name, $result->name);
        self::assertSame($dto->earningRate, $result->earningRate);
    }

    #[Test]
    public function itMapsTiersFromDtoToResource(): void
    {
        $tier = new LoyaltyTierDTO(
            id: '22222222-2222-4222-8222-222222222222',
            name: 'Bronze',
            threshold: 0,
            discountPercentage: 3,
            freeShippingMinOrder: null,
            sortOrder: 1,
        );
        $dto = $this->buildLoyaltyProgramDTO(tiers: [$tier]);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($data, $operation, ['id' => self::PROGRAM_ID], []);

        self::assertCount(1, $result->tiers);
        self::assertSame('Bronze', $result->tiers[0]['name']);
        self::assertSame(0, $result->tiers[0]['threshold']);
    }

    // ---------------------------------------------------------------
    // Validation guards – data type
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotLoyaltyProgramResource(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Expected LoyaltyProgramResource');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(new \stdClass(), $operation, ['id' => self::PROGRAM_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – URI variables
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenProgramIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Program ID is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, [], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – tenant
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new UpdateLoyaltyProgramProcessor(
            $this->commandBus,
            $this->queryBus,
            $tenantContext,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process($this->buildValidResource(), $operation, ['id' => self::PROGRAM_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – required fields
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenNameIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->name = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Name is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenNameIsEmpty(): void
    {
        $data = $this->buildValidResource();
        $data->name = '';

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Name is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenEarningRateIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->earningRate = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Earning rate is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenMinOrderValueIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->minOrderValue = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Min order value is required with amount and currency');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenMinOrderValueMissingCurrency(): void
    {
        $data = $this->buildValidResource();
        $data->minOrderValue = ['amount' => 1000];

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Min order value is required with amount and currency');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::PROGRAM_ID], []);
    }

    // ---------------------------------------------------------------
    // Handler failure paths
    // ---------------------------------------------------------------

    #[Test]
    public function itRethrowsHttpExceptionFromHandlerFailedException(): void
    {
        $httpException = new BadRequestHttpException('Loyalty program conflict');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$httpException]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Loyalty program conflict');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itRethrowsHandlerFailedExceptionWhenPreviousIsNotHttpException(): void
    {
        $cause = new \RuntimeException('Domain error');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$cause]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(HandlerFailedException::class);

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Loyalty program not found after update');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['id' => self::PROGRAM_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildValidResource(): LoyaltyProgramResource
    {
        $resource = new LoyaltyProgramResource();
        $resource->name = 'Updated Program';
        $resource->earningRate = 2.0;
        $resource->minOrderValue = ['amount' => 500, 'currency' => 'EUR'];
        $resource->redemptionRule = ['rate' => 50, 'currency' => 'EUR'];
        $resource->description = 'Updated description';
        $resource->validityDays = 180;

        return $resource;
    }

    /**
     * @param array<LoyaltyTierDTO> $tiers
     */
    private function buildLoyaltyProgramDTO(array $tiers = []): LoyaltyProgramDTO
    {
        return new LoyaltyProgramDTO(
            id: self::PROGRAM_ID,
            tenantId: self::TENANT_ID,
            name: 'Updated Program',
            description: 'Updated description',
            earningRate: 2.0,
            minOrderValue: ['amount' => 500, 'currency' => 'EUR'],
            redemptionRule: ['rate' => 50, 'currency' => 'EUR'],
            validityDays: 180,
            isActive: true,
            tiers: $tiers,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-06-01T00:00:00+00:00',
        );
    }
}
