<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\CreateLoyaltyProgram\CreateLoyaltyProgramCommand;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\DTO\LoyaltyTierDTO;
use App\Customer\Application\Query\GetLoyaltyProgram\GetLoyaltyProgramQuery;
use App\Customer\Presentation\Api\Processor\CreateLoyaltyProgramProcessor;
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

#[CoversClass(CreateLoyaltyProgramProcessor::class)]
final class CreateLoyaltyProgramProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PROGRAM_ID = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private CreateLoyaltyProgramProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new CreateLoyaltyProgramProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesCreateCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildLoyaltyProgramDTO();

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CreateLoyaltyProgramCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetLoyaltyProgramQuery::class))
            ->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process($data, $operation, [], []);

        self::assertInstanceOf(LoyaltyProgramResource::class, $result);
        self::assertSame($dto->id, $result->id);
        self::assertSame($dto->name, $result->name);
        self::assertSame($dto->earningRate, $result->earningRate);
        self::assertSame($dto->description, $result->description);
        self::assertSame($dto->validityDays, $result->validityDays);
        self::assertSame($dto->createdAt, $result->createdAt);
        self::assertSame($dto->updatedAt, $result->updatedAt);
    }

    #[Test]
    public function itMapsTiersCorrectly(): void
    {
        $tier = new LoyaltyTierDTO(
            id: 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            name: 'Platinum',
            threshold: 1000,
            discountPercentage: 20,
            freeShippingMinOrder: ['amount' => 0, 'currency' => 'USD'],
            sortOrder: 3,
        );
        $dto = $this->buildLoyaltyProgramDTO(tiers: [$tier]);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($data, $operation, [], []);

        self::assertCount(1, $result->tiers);
        self::assertSame('Platinum', $result->tiers[0]['name']);
        self::assertSame(1000, $result->tiers[0]['threshold']);
        self::assertSame(20, $result->tiers[0]['discountPercentage']);
        self::assertSame(3, $result->tiers[0]['sortOrder']);
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
        $this->processor->process(new \stdClass(), $operation, [], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – tenant
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new CreateLoyaltyProgramProcessor(
            $this->commandBus,
            $this->queryBus,
            $tenantContext,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process($this->buildValidResource(), $operation, [], []);
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
        $this->expectExceptionMessage('Name and earning rate are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenEarningRateIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->earningRate = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Name and earning rate are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenMinOrderValueIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->minOrderValue = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Minimum order value (amount and currency) is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenMinOrderValueMissingAmount(): void
    {
        $data = $this->buildValidResource();
        $data->minOrderValue = ['currency' => 'USD'];

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Minimum order value (amount and currency) is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenRedemptionRuleIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->redemptionRule = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Redemption rule (rate and currency) is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenRedemptionRuleMissingRate(): void
    {
        $data = $this->buildValidResource();
        $data->redemptionRule = ['currency' => 'USD'];

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Redemption rule (rate and currency) is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    // ---------------------------------------------------------------
    // Handler failure paths
    // ---------------------------------------------------------------

    #[Test]
    public function itRethrowsHttpExceptionFromHandlerFailedException(): void
    {
        $httpException = new BadRequestHttpException('Program already exists');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$httpException]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Program already exists');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, [], []);
    }

    #[Test]
    public function itRethrowsHandlerFailedExceptionWhenPreviousIsNotHttpException(): void
    {
        $cause = new \RuntimeException('Internal error');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$cause]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(HandlerFailedException::class);

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Loyalty program not found after creation');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, [], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildValidResource(): LoyaltyProgramResource
    {
        $resource = new LoyaltyProgramResource();
        $resource->name = 'Gold Rewards';
        $resource->earningRate = 1.5;
        $resource->minOrderValue = ['amount' => 1000, 'currency' => 'USD'];
        $resource->redemptionRule = ['rate' => 100, 'currency' => 'USD'];

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
            name: 'Gold Rewards',
            description: 'Earn points on every purchase',
            earningRate: 1.5,
            minOrderValue: ['amount' => 1000, 'currency' => 'USD'],
            redemptionRule: ['rate' => 100, 'currency' => 'USD'],
            validityDays: 365,
            isActive: false,
            tiers: $tiers,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );
    }
}
