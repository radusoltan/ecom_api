<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Application\Query\GetCustomerByIdQuery;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Customer\Presentation\Api\Provider\CustomerItemProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(CustomerItemProvider::class)]
final class CustomerItemProviderTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '55555555-5555-4555-8555-555555555555';

    private MessageBusInterface $queryBus;
    private CustomerItemProvider $provider;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->provider = new CustomerItemProvider($this->queryBus);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsCustomerEntityWhenFound(): void
    {
        $dto = $this->buildCustomerDTO();
        $envelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);

        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetCustomerByIdQuery::class))
            ->willReturn($envelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            ['id' => self::CUSTOMER_ID],
            ['tenant_id' => self::TENANT_ID]
        );

        self::assertInstanceOf(CustomerEntity::class, $result);
        self::assertSame(self::CUSTOMER_ID, $result->getId());
        self::assertSame('test@example.com', $result->getEmail());
    }

    #[Test]
    public function itReturnsNullWhenNoHandledStamp(): void
    {
        $this->queryBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass())); // no HandledStamp

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            ['id' => self::CUSTOMER_ID],
            ['tenant_id' => self::TENANT_ID]
        );

        self::assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenHandledStampContainsNull(): void
    {
        $envelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus
            ->method('dispatch')
            ->willReturn($envelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            ['id' => self::CUSTOMER_ID],
            ['tenant_id' => self::TENANT_ID]
        );

        self::assertNull($result);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation, [], ['tenant_id' => self::TENANT_ID]);
    }

    #[Test]
    public function itThrowsWhenTenantIdMissingFromContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation, ['id' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildCustomerDTO(): CustomerDTO
    {
        return new CustomerDTO(
            id: self::CUSTOMER_ID,
            tenantId: self::TENANT_ID,
            email: 'test@example.com',
            firstName: 'Test',
            lastName: 'User',
            fullName: 'Test User',
            phoneNumber: null,
            segment: 'standard',
            loyaltyPoints: 0,
            isActive: true,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00',
        );
    }
}
