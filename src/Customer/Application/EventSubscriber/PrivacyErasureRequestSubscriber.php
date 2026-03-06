<?php

declare(strict_types=1);

namespace App\Customer\Application\EventSubscriber;

use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Privacy\Domain\Event\DataErasureRequested;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles Privacy context erasure DSR by creating a Customer DeletionRequest.
 *
 * When Privacy context receives a GDPR erasure request, this subscriber
 * creates and auto-confirms a DeletionRequest in the Customer context
 * to trigger the actual anonymization workflow.
 */
final readonly class PrivacyErasureRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            DataErasureRequested::class => 'onDataErasureRequested',
        ];
    }

    public function onDataErasureRequested(DataErasureRequested $event): void
    {
        // Idempotency: check for existing pending deletion request
        $existing = $this->deletionRequestRepository->findPendingByCustomerId(
            $event->customerId,
            $event->tenantId,
        );

        if (null !== $existing) {
            $this->logger->info('Deletion request already exists for customer, skipping', [
                'customerId' => $event->customerId->toString(),
                'existingRequestId' => $existing->id()->toString(),
                'privacyRequestId' => $event->requestId->toString(),
            ]);

            return;
        }

        // Create and auto-confirm deletion request
        $deletionRequest = DeletionRequest::create(
            DeletionRequestId::generate(),
            $event->customerId,
            $event->tenantId,
            'GDPR erasure request via Privacy context (DSR: '.$event->requestId->toString().')',
        );

        // Auto-confirm since Privacy context has already validated the DSR
        $deletionRequest->confirm();

        $this->deletionRequestRepository->save($deletionRequest);

        $this->logger->info('DeletionRequest created from Privacy erasure DSR', [
            'deletionRequestId' => $deletionRequest->id()->toString(),
            'customerId' => $event->customerId->toString(),
            'privacyDsrId' => $event->requestId->toString(),
        ]);
    }
}
