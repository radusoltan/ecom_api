<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\EventSubscriber;

use App\Customer\Application\EventSubscriber\PrivacyDataAccessSubscriber;
use App\Customer\Application\Message\GenerateDataExportMessage;
use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('customer')]
final class PrivacyDataAccessSubscriberTest extends TestCase
{
    private DataExportRequestRepositoryInterface&MockObject $exportRequestRepository;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private PrivacyDataAccessSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->exportRequestRepository = $this->createMock(DataExportRequestRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PrivacyDataAccessSubscriber(
            $this->exportRequestRepository,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testCreatesExportRequestForAccessType(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $requestId = DataSubjectRequestId::generate();

        $this->exportRequestRepository
            ->expects(self::once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->exportRequestRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataExportRequest $request) use ($customerId, $tenantId) {
                return $request->customerId()->equals($customerId)
                    && $request->tenantId()->equals($tenantId);
            }));

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GenerateDataExportMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $event = new DataSubjectRequestSubmitted(
            requestId: $requestId,
            customerId: $customerId,
            requestType: RequestType::access(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onDataSubjectRequestSubmitted($event);
    }

    public function testCreatesExportRequestForPortabilityType(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $requestId = DataSubjectRequestId::generate();

        $this->exportRequestRepository
            ->expects(self::once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->exportRequestRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataExportRequest $request) use ($customerId, $tenantId) {
                return $request->customerId()->equals($customerId)
                    && $request->tenantId()->equals($tenantId);
            }));

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GenerateDataExportMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $event = new DataSubjectRequestSubmitted(
            requestId: $requestId,
            customerId: $customerId,
            requestType: RequestType::portability(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onDataSubjectRequestSubmitted($event);
    }

    public function testIgnoresErasureType(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $requestId = DataSubjectRequestId::generate();

        $this->exportRequestRepository
            ->expects(self::never())
            ->method('findPendingByCustomerId');

        $this->exportRequestRepository
            ->expects(self::never())
            ->method('save');

        $this->messageBus
            ->expects(self::never())
            ->method('dispatch');

        $event = new DataSubjectRequestSubmitted(
            requestId: $requestId,
            customerId: $customerId,
            requestType: RequestType::erasure(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onDataSubjectRequestSubmitted($event);
    }

    public function testIdempotentWhenExportRequestExists(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $requestId = DataSubjectRequestId::generate();

        $existingRequest = DataExportRequest::create(
            customerId: $customerId,
            tenantId: $tenantId,
        );

        $this->exportRequestRepository
            ->expects(self::once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn($existingRequest);

        $this->exportRequestRepository
            ->expects(self::never())
            ->method('save');

        $this->messageBus
            ->expects(self::never())
            ->method('dispatch');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Data export already pending for customer, skipping',
                self::arrayHasKey('customerId'),
            );

        $event = new DataSubjectRequestSubmitted(
            requestId: $requestId,
            customerId: $customerId,
            requestType: RequestType::access(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onDataSubjectRequestSubmitted($event);
    }
}
