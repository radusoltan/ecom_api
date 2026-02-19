<?php

declare(strict_types=1);

namespace App\Privacy\Application\EventSubscriber;

use App\Customer\Domain\Event\DataExportCompleted;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Completes Privacy access/portability DSR when Customer export is done.
 *
 * When Customer context finishes generating a data export,
 * this subscriber finds the corresponding Privacy DSR and marks it complete
 * with the export data.
 */
final readonly class CustomerDataExportCompletedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $dsrRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            DataExportCompleted::class => 'onDataExportCompleted',
        ];
    }

    public function onDataExportCompleted(DataExportCompleted $event): void
    {
        $customerId = $event->customerId();

        // Find pending/under_review access or portability DSR for this customer
        $dsrs = $this->dsrRepository->findByCustomerId($customerId);

        $matchingDsr = null;
        foreach ($dsrs as $dsr) {
            if (($dsr->requestType()->equals(RequestType::access())
                || $dsr->requestType()->equals(RequestType::portability()))
                && !$dsr->status()->isFinal()) {
                $matchingDsr = $dsr;

                break;
            }
        }

        if (null === $matchingDsr) {
            $this->logger->debug('No pending Privacy access/portability DSR found for export', [
                'customerId' => $customerId->toString(),
            ]);

            return;
        }

        // Read the export file to get the data
        $exportData = $this->readExportFile($event->filePath());

        if (null === $exportData) {
            $this->logger->error('Failed to read export file for Privacy DSR completion', [
                'filePath' => $event->filePath(),
                'dsrId' => $matchingDsr->id()->toString(),
            ]);

            return;
        }

        // Transition DSR to completed with export data
        if ($matchingDsr->status()->equals(RequestStatus::pending())) {
            $matchingDsr->startReview('Auto-completed via Customer data export');
        }

        $matchingDsr->complete($exportData);
        $this->dsrRepository->save($matchingDsr);

        $this->logger->info('Privacy access/portability DSR completed with export data', [
            'dsrId' => $matchingDsr->id()->toString(),
            'customerId' => $customerId->toString(),
            'filePath' => $event->filePath(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readExportFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        if (false === $content) {
            return null;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }
}
