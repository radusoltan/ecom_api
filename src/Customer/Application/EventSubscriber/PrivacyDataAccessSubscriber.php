<?php

declare(strict_types=1);

namespace App\Customer\Application\EventSubscriber;

use App\Customer\Application\Message\GenerateDataExportMessage;
use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles Privacy context access/portability DSR by creating Customer DataExportRequest.
 *
 * When Privacy context receives a GDPR access or portability request,
 * this subscriber creates a DataExportRequest in Customer context and
 * dispatches async processing.
 */
final readonly class PrivacyDataAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DataExportRequestRepositoryInterface $exportRequestRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            DataSubjectRequestSubmitted::class => 'onDataSubjectRequestSubmitted',
        ];
    }

    public function onDataSubjectRequestSubmitted(DataSubjectRequestSubmitted $event): void
    {
        // Only handle access and portability requests
        $type = $event->requestType->value();
        if ('access' !== $type && 'portability' !== $type) {
            return;
        }

        // Check for existing pending export
        $existing = $this->exportRequestRepository->findPendingByCustomerId(
            $event->customerId,
            $event->tenantId,
        );

        if (null !== $existing) {
            $this->logger->info('Data export already pending for customer, skipping', [
                'customerId' => $event->customerId->toString(),
                'existingRequestId' => $existing->id()->toString(),
            ]);

            return;
        }

        // Create data export request
        $exportRequest = DataExportRequest::create(
            $event->customerId,
            $event->tenantId,
        );

        $this->exportRequestRepository->save($exportRequest);

        // Dispatch async processing
        $this->messageBus->dispatch(new GenerateDataExportMessage(
            $exportRequest->id()->toString(),
            $event->customerId->toString(),
            $event->tenantId->toString(),
        ));

        $this->logger->info('DataExportRequest created from Privacy DSR', [
            'exportRequestId' => $exportRequest->id()->toString(),
            'customerId' => $event->customerId->toString(),
            'privacyDsrId' => $event->requestId->toString(),
            'requestType' => $type,
        ]);
    }
}
