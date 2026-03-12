<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\RedeemPoints\RedeemPointsCommand;
use App\Customer\Application\DTO\RedeemPointsResult;
use App\Customer\Presentation\Api\Processor\RedeemPointsProcessor;
use App\Customer\Presentation\Api\Resource\CustomerLoyaltyResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(RedeemPointsProcessor::class)]
final class RedeemPointsProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '33333333-3333-4333-8333-333333333333';

    private MessageBusInterface $commandBus;
    private TenantContextInterface $tenantContext;
    private RedeemPointsProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->processor = new RedeemPointsProcessor($this->commandBus, $this->tenantContext);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesRedeemCommandAndReturnsMappedResource(): void
    {
        $redeemResult = new RedeemPointsResult(
            pointsRedeemed: 200,
            discountAmountMinorUnits: 200,
            discountCurrency: 'USD',
            remainingBalance: 800,
        );

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($redeemResult, 'handler')]);
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RedeemPointsCommand::class))
            ->willReturn($envelope);

        $data = new CustomerLoyaltyResource();
        $data->pointsToRedeem = 200;

        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(CustomerLoyaltyResource::class, $result);
        self::assertSame(200, $result->pointsRedeemed);
        self::assertSame(200, $result->discountAmount);
        self::assertSame('USD', $result->discountCurrency);
        self::assertSame(800, $result->remainingBalance);
        self::assertSame(self::CUSTOMER_ID, $result->customerId);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotCustomerLoyaltyResource(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $operation = $this->createMock(Operation::class);
        $this->processor->process(new \stdClass(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $data = new CustomerLoyaltyResource();
        $data->pointsToRedeem = 100;
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new RedeemPointsProcessor($this->commandBus, $tenantContext);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $data = new CustomerLoyaltyResource();
        $data->pointsToRedeem = 100;
        $operation = $this->createMock(Operation::class);
        $processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenPointsToRedeemIsNull(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Points to redeem is required');

        $data = new CustomerLoyaltyResource();
        // pointsToRedeem is null by default
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenPointsToRedeemIsNotPositive(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Points to redeem must be positive');

        $data = new CustomerLoyaltyResource();
        $data->pointsToRedeem = 0;
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenCommandHandlerReturnsNoStamp(): void
    {
        $this->commandBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass())); // no HandledStamp

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for command');

        $data = new CustomerLoyaltyResource();
        $data->pointsToRedeem = 100;
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }
}
