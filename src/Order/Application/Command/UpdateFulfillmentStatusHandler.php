<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use Psr\Log\LoggerInterface;

/**
 * Update Fulfillment Status Command Handler
 *
 * Transitions a fulfillment through its workflow states.
 */
final readonly class UpdateFulfillmentStatusHandler
{
    public function __construct(
        private FulfillmentRepositoryInterface $fulfillmentRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateFulfillmentStatus $command): void
    {
        $fulfillment = $this->fulfillmentRepository->findById($command->fulfillmentId);

        if ($fulfillment === null) {
            throw new \DomainException(sprintf('Fulfillment not found: %s', $command->fulfillmentId->toString()));
        }

        // Verify tenant ownership
        if (!$fulfillment->tenantId()->equals($command->tenantId)) {
            throw new \DomainException('Fulfillment does not belong to this tenant');
        }

        // Transition to new status based on the target status
        $newStatus = $command->newStatus;

        if ($newStatus->equals(FulfillmentStatus::picking())) {
            $fulfillment->startPicking();
        } elseif ($newStatus->equals(FulfillmentStatus::packing())) {
            $fulfillment->startPacking();
        } elseif ($newStatus->equals(FulfillmentStatus::shipping())) {
            if ($command->carrier === null || $command->trackingNumber === null) {
                throw new \DomainException('Carrier and tracking number are required for shipping status');
            }
            $fulfillment->ship($command->carrier, $command->trackingNumber);
        } elseif ($newStatus->equals(FulfillmentStatus::delivered())) {
            $fulfillment->markAsDelivered();
        } elseif ($newStatus->equals(FulfillmentStatus::cancelled())) {
            $fulfillment->cancel();
        } elseif ($newStatus->equals(FulfillmentStatus::failed())) {
            if ($command->failureReason === null) {
                throw new \DomainException('Failure reason is required for failed status');
            }
            $fulfillment->markAsFailed($command->failureReason);
        } else {
            throw new \DomainException(sprintf('Unsupported status transition: %s', $newStatus->value()));
        }

        $this->fulfillmentRepository->save($fulfillment);

        $this->logger->info('Fulfillment status updated', [
            'fulfillment_id' => $command->fulfillmentId->toString(),
            'new_status' => $newStatus->value(),
            'tenant_id' => $command->tenantId->toString(),
        ]);
    }
}
