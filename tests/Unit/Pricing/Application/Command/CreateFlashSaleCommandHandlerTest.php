<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Pricing\Application\Command\CreateFlashSale\CreateFlashSaleCommand;
use App\Pricing\Application\Command\CreateFlashSale\CreateFlashSaleCommandHandler;
use App\Pricing\Application\Message\ActivateFlashSaleMessage;
use App\Pricing\Application\Message\DeactivateFlashSaleMessage;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(CreateFlashSaleCommandHandler::class)]
final class CreateFlashSaleCommandHandlerTest extends TestCase
{
    private FlashSaleRepositoryInterface $repository;
    private MessageBusInterface $messageBus;
    private CreateFlashSaleCommandHandler $handler;
    private TenantId $tenantId;
    private FlashSaleId $flashSaleId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FlashSaleRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->handler = new CreateFlashSaleCommandHandler($this->repository, $this->messageBus);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->flashSaleId = FlashSaleId::generate();
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesFlashSaleAndSchedulesBothMessages(): void
    {
        $start = (new \DateTimeImmutable())->modify('+1 hour');
        $end = (new \DateTimeImmutable())->modify('+3 hours');

        $dispatchedMessages = [];
        $this->messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message, array $stamps) use (&$dispatchedMessages): Envelope {
                $dispatchedMessages[] = ['message' => $message, 'stamps' => $stamps];

                return new Envelope($message);
            });

        $this->repository
            ->expects(self::once())
            ->method('save');

        $command = new CreateFlashSaleCommand(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Summer Flash Sale',
            productIds: ['00000000-0000-4000-8000-000000000010'],
            discountType: 'percentage',
            discountValue: 20.0,
            startTime: $start->format('Y-m-d H:i:s'),
            endTime: $end->format('Y-m-d H:i:s'),
        );

        ($this->handler)($command);

        self::assertCount(2, $dispatchedMessages);
        self::assertInstanceOf(ActivateFlashSaleMessage::class, $dispatchedMessages[0]['message']);
        self::assertInstanceOf(DeactivateFlashSaleMessage::class, $dispatchedMessages[1]['message']);
    }

    #[Test]
    public function itDispatchesMessagesWithDelayStamps(): void
    {
        $start = (new \DateTimeImmutable())->modify('+2 hours');
        $end = (new \DateTimeImmutable())->modify('+4 hours');

        $receivedStamps = [];
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(function (object $message, array $stamps) use (&$receivedStamps): Envelope {
                $receivedStamps[] = $stamps;

                return new Envelope($message);
            });

        $this->repository->method('save');

        $command = new CreateFlashSaleCommand(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Delayed Flash Sale',
            productIds: ['00000000-0000-4000-8000-000000000010'],
            discountType: 'percentage',
            discountValue: 10.0,
            startTime: $start->format('Y-m-d H:i:s'),
            endTime: $end->format('Y-m-d H:i:s'),
        );

        ($this->handler)($command);

        foreach ($receivedStamps as $stamps) {
            self::assertNotEmpty($stamps);
            self::assertInstanceOf(DelayStamp::class, $stamps[0]);
            self::assertGreaterThan(0, $stamps[0]->getDelay());
        }
    }

    #[Test]
    public function itPassesCorrectFlashSaleIdInMessages(): void
    {
        $start = (new \DateTimeImmutable())->modify('+1 hour');
        $end = (new \DateTimeImmutable())->modify('+3 hours');

        $dispatchedMessages = [];
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(function (object $message, array $stamps) use (&$dispatchedMessages): Envelope {
                $dispatchedMessages[] = $message;

                return new Envelope($message);
            });

        $this->repository->method('save');

        $command = new CreateFlashSaleCommand(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'ID Check Sale',
            productIds: ['00000000-0000-4000-8000-000000000010'],
            discountType: 'percentage',
            discountValue: 15.0,
            startTime: $start->format('Y-m-d H:i:s'),
            endTime: $end->format('Y-m-d H:i:s'),
        );

        ($this->handler)($command);

        self::assertSame(
            $this->flashSaleId->toString(),
            $dispatchedMessages[0]->getFlashSaleId()->toString()
        );
        self::assertSame(
            $this->flashSaleId->toString(),
            $dispatchedMessages[1]->getFlashSaleId()->toString()
        );
    }

    // -----------------------------------------------------------------------
    // Validation errors → HTTP 400
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsBadRequestForEmptyProductIds(): void
    {
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->repository->expects(self::never())->method('save');

        $start = (new \DateTimeImmutable())->modify('+1 hour');
        $end = (new \DateTimeImmutable())->modify('+3 hours');

        $command = new CreateFlashSaleCommand(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'No Products Sale',
            productIds: [], // invalid
            discountType: 'percentage',
            discountValue: 20.0,
            startTime: $start->format('Y-m-d H:i:s'),
            endTime: $end->format('Y-m-d H:i:s'),
        );

        $this->expectException(BadRequestHttpException::class);

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsBadRequestForInvalidDiscountType(): void
    {
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->repository->expects(self::never())->method('save');

        $start = (new \DateTimeImmutable())->modify('+1 hour');
        $end = (new \DateTimeImmutable())->modify('+3 hours');

        $command = new CreateFlashSaleCommand(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Bad Discount Sale',
            productIds: ['00000000-0000-4000-8000-000000000010'],
            discountType: 'invalid_type', // invalid
            discountValue: 20.0,
            startTime: $start->format('Y-m-d H:i:s'),
            endTime: $end->format('Y-m-d H:i:s'),
        );

        $this->expectException(BadRequestHttpException::class);

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsBadRequestForEndTimeBeforeStartTime(): void
    {
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->repository->expects(self::never())->method('save');

        $start = (new \DateTimeImmutable())->modify('+3 hours');
        $end = (new \DateTimeImmutable())->modify('+1 hour'); // before start

        $command = new CreateFlashSaleCommand(
            flashSaleId: $this->flashSaleId,
            tenantId: $this->tenantId,
            name: 'Invalid Time Sale',
            productIds: ['00000000-0000-4000-8000-000000000010'],
            discountType: 'percentage',
            discountValue: 20.0,
            startTime: $start->format('Y-m-d H:i:s'),
            endTime: $end->format('Y-m-d H:i:s'),
        );

        $this->expectException(BadRequestHttpException::class);

        ($this->handler)($command);
    }
}
