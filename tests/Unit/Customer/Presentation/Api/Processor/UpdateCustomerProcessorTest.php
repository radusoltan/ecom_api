<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\UpdateCustomerCommand;
use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Customer\Presentation\Api\Processor\UpdateCustomerProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(UpdateCustomerProcessor::class)]
final class UpdateCustomerProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '22222222-2222-4222-8222-222222222222';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private UpdateCustomerProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->processor = new UpdateCustomerProcessor($this->commandBus, $this->queryBus);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesUpdateCommandAndReturnsUpdatedEntity(): void
    {
        $dto = new CustomerDTO(
            id: self::CUSTOMER_ID,
            tenantId: self::TENANT_ID,
            email: 'updated@example.com',
            firstName: 'Updated',
            lastName: 'Name',
            fullName: 'Updated Name',
            phoneNumber: '+40700000000',
            segment: 'vip',
            loyaltyPoints: 500,
            isActive: true,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-06-01 00:00:00',
        );

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdateCustomerCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $data = new CustomerEntity();
        $data->setFirstName('Updated');
        $data->setLastName('Name');

        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['id' => self::CUSTOMER_ID],
            ['tenant_id' => self::TENANT_ID]
        );

        self::assertInstanceOf(CustomerEntity::class, $result);
        self::assertSame('Updated', $result->getFirstName());
        self::assertSame('Name', $result->getLastName());
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
        $data->setFirstName('X');
        $data->setLastName('Y');
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenFirstOrLastNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name and last name are required');

        $data = new CustomerEntity();
        // firstName and lastName default to empty strings
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::CUSTOMER_ID], ['tenant_id' => self::TENANT_ID]);
    }

    #[Test]
    public function itThrowsWhenCustomerNotFoundAfterUpdate(): void
    {
        $this->commandBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus
            ->method('dispatch')
            ->willReturn($queryEnvelope);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer not found after update');

        $data = new CustomerEntity();
        $data->setFirstName('First');
        $data->setLastName('Last');
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['id' => self::CUSTOMER_ID], ['tenant_id' => self::TENANT_ID]);
    }
}
