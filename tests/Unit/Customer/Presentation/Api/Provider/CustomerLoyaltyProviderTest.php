<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\DTO\CustomerLoyaltyStatusDTO;
use App\Customer\Application\Query\GetCustomerLoyaltyStatus\GetCustomerLoyaltyStatusQuery;
use App\Customer\Presentation\Api\Provider\CustomerLoyaltyProvider;
use App\Customer\Presentation\Api\Resource\CustomerLoyaltyResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(CustomerLoyaltyProvider::class)]
final class CustomerLoyaltyProviderTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '66666666-6666-4666-8666-666666666666';

    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private CustomerLoyaltyProvider $provider;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->provider = new CustomerLoyaltyProvider($this->queryBus, $this->tenantContext);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsLoyaltyResourceMappedFromDto(): void
    {
        $dto = new CustomerLoyaltyStatusDTO(
            customerId: self::CUSTOMER_ID,
            currentPoints: 2500,
            currentTier: 'Silver',
            nextTier: 'Gold',
            pointsToNextTier: 2500,
            lifetimePointsEarned: 10000,
            lifetimePointsRedeemed: 7500,
            currentTierDiscount: 10,
            programName: 'Rewards',
            programIsActive: true,
        );

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetCustomerLoyaltyStatusQuery::class))
            ->willReturn($envelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(CustomerLoyaltyResource::class, $result);
        self::assertSame(self::CUSTOMER_ID, $result->customerId);
        self::assertSame(2500, $result->currentPoints);
        self::assertSame('Silver', $result->currentTier);
        self::assertSame('Gold', $result->nextTier);
        self::assertSame(2500, $result->pointsToNextTier);
        self::assertSame(10000, $result->lifetimePointsEarned);
        self::assertTrue($result->programIsActive);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation, [], []);
    }

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $provider = new CustomerLoyaltyProvider($this->queryBus, $tenantContext);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $provider->provide($operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsNotFoundWhenDtoIsNull(): void
    {
        $envelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus
            ->method('dispatch')
            ->willReturn($envelope);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Customer loyalty status not found');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenNoHandledStampPresent(): void
    {
        $this->queryBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass())); // no HandledStamp

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation, ['customerId' => self::CUSTOMER_ID], []);
    }
}
