<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\DeactivateLoyaltyProgram\DeactivateLoyaltyProgramCommand;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\DTO\LoyaltyTierDTO;
use App\Customer\Application\Query\GetLoyaltyProgramById\GetLoyaltyProgramByIdQuery;
use App\Customer\Presentation\Api\Processor\DeactivateLoyaltyProgramProcessor;
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

#[CoversClass(DeactivateLoyaltyProgramProcessor::class)]
final class DeactivateLoyaltyProgramProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PROGRAM_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private DeactivateLoyaltyProgramProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new DeactivateLoyaltyProgramProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesDeactivateCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildLoyaltyProgramDTO(isActive: false);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DeactivateLoyaltyProgramCommand::class))
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
        self::assertFalse($result->isActive);
    }

    #[Test]
    public function itMapsTiersFromDtoToResource(): void
    {
        $tier = new LoyaltyTierDTO(
            id: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            name: 'Silver',
            threshold: 200,
            discountPercentage: 5,
            freeShippingMinOrder: null,
            sortOrder: 2,
        );
        $dto = $this->buildLoyaltyProgramDTO(isActive: false, tiers: [$tier]);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);

        self::assertCount(1, $result->tiers);
        self::assertSame('Silver', $result->tiers[0]['name']);
        self::assertNull($result->tiers[0]['freeShippingMinOrder']);
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

        $processor = new DeactivateLoyaltyProgramProcessor(
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
        $httpException = new BadRequestHttpException('Program not found');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$httpException]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Program not found');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);
    }

    #[Test]
    public function itRethrowsHandlerFailedExceptionWhenPreviousIsNotHttpException(): void
    {
        $cause = new \RuntimeException('Domain error');
        $handlerFailed = new HandlerFailedException(new Envelope(new \stdClass()), [$cause]);

        $this->commandBus->method('dispatch')->willThrowException($handlerFailed);

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
        $this->expectExceptionMessage('Loyalty program not found after deactivation');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['id' => self::PROGRAM_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @param array<LoyaltyTierDTO> $tiers
     */
    private function buildLoyaltyProgramDTO(bool $isActive = false, array $tiers = []): LoyaltyProgramDTO
    {
        return new LoyaltyProgramDTO(
            id: self::PROGRAM_ID,
            tenantId: self::TENANT_ID,
            name: 'Test Program',
            description: null,
            earningRate: 2.0,
            minOrderValue: ['amount' => 500, 'currency' => 'EUR'],
            redemptionRule: ['rate' => 50, 'currency' => 'EUR'],
            validityDays: null,
            isActive: $isActive,
            tiers: $tiers,
            createdAt: '2024-06-01T00:00:00+00:00',
            updatedAt: '2024-06-01T00:00:00+00:00',
        );
    }
}
