<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\EventSubscriber;

use App\Customer\Application\EventSubscriber\PrivacyErasureRequestSubscriber;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Privacy\Domain\Event\DataErasureRequested;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('customer')]
final class PrivacyErasureRequestSubscriberTest extends TestCase
{
    private DeletionRequestRepositoryInterface&MockObject $deletionRequestRepository;
    private LoggerInterface&MockObject $logger;
    private PrivacyErasureRequestSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->deletionRequestRepository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PrivacyErasureRequestSubscriber(
            $this->deletionRequestRepository,
            $this->logger,
        );
    }

    public function testCreatesDeletionRequestFromErasureDsr(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $privacyRequestId = DataSubjectRequestId::generate();

        $this->deletionRequestRepository
            ->expects(self::once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->deletionRequestRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DeletionRequest $request) use ($customerId, $tenantId) {
                return $request->customerId()->equals($customerId)
                    && $request->tenantId()->equals($tenantId);
            }));

        $event = new DataErasureRequested(
            requestId: $privacyRequestId,
            customerId: $customerId,
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onDataErasureRequested($event);
    }

    public function testIdempotentWhenDeletionRequestExists(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $privacyRequestId = DataSubjectRequestId::generate();

        $existingRequest = DeletionRequest::create(
            id: DeletionRequestId::generate(),
            customerId: $customerId,
            tenantId: $tenantId,
            reason: 'Existing deletion request',
        );

        $this->deletionRequestRepository
            ->expects(self::once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn($existingRequest);

        $this->deletionRequestRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Deletion request already exists for customer, skipping',
                self::arrayHasKey('customerId'),
            );

        $event = new DataErasureRequested(
            requestId: $privacyRequestId,
            customerId: $customerId,
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onDataErasureRequested($event);
    }

    public function testDeletionRequestAutoConfirmed(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $privacyRequestId = DataSubjectRequestId::generate();

        $this->deletionRequestRepository
            ->method('findPendingByCustomerId')
            ->willReturn(null);

        $savedRequest = null;
        $this->deletionRequestRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DeletionRequest $request) use (&$savedRequest) {
                $savedRequest = $request;

                return true;
            }));

        $event = new DataErasureRequested(
            requestId: $privacyRequestId,
            customerId: $customerId,
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onDataErasureRequested($event);

        self::assertNotNull($savedRequest);
        self::assertSame(DeletionStatus::CONFIRMED, $savedRequest->status());
        self::assertNotNull($savedRequest->confirmedAt());
    }
}
