<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\DeactivateCustomerCommand;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Customer\Presentation\Api\Processor\DeactivateCustomerProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(DeactivateCustomerProcessor::class)]
final class DeactivateCustomerProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private DeactivateCustomerProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new DeactivateCustomerProcessor($this->commandBus, $this->queryBus);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesDeactivateCommandAndReturnsCustomerEntity(): void
    {
        $dto = $this->buildCustomerDTO(isActive: false);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DeactivateCustomerCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $data = new CustomerEntity();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['id' => self::CUSTOMER_ID],
            ['tenant_id' => self::TENANT_ID]
        );

        self::assertInstanceOf(CustomerEntity::class, $result);
        self::assertFalse($result->isActive());
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotCustomerEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $operation = $this->createMock(Operation::class);
        $this->processor->process(new \stdClass(), $operation, ['id' => self::CUSTOMER_ID], ['tenant_id' => self::TENANT_ID]);
    }

    #[Test]
    public function itThrowsWhenTenantIdMissingFromContext(): void
    {
        $this->expectException(\RuntimeException::class);

        $data = new CustomerEntity();
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenCustomerNotFoundAfterDeactivation(): void
    {
        $this->commandBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer not found after deactivation');

        $data = new CustomerEntity();
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::CUSTOMER_ID], ['tenant_id' => self::TENANT_ID]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildCustomerDTO(bool $isActive): CustomerDTO
    {
        return new CustomerDTO(
            id: self::CUSTOMER_ID,
            tenantId: self::TENANT_ID,
            email: 'test@example.com',
            firstName: 'Jane',
            lastName: 'Doe',
            fullName: 'Jane Doe',
            phoneNumber: null,
            segment: 'standard',
            loyaltyPoints: 100,
            isActive: $isActive,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00',
        );
    }
}
