<?php

declare(strict_types=1);

namespace App\Privacy\Application\EventSubscriber;

use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Completes Privacy erasure DSR when Customer deletion is done.
 *
 * When Customer context finishes anonymizing customer data,
 * this subscriber finds the corresponding Privacy DSR and marks it complete.
 */
final readonly class CustomerDeletionCompletedSubscriber implements EventSubscriberInterface
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
            AccountDeletionCompleted::class => 'onAccountDeletionCompleted',
        ];
    }

    public function onAccountDeletionCompleted(AccountDeletionCompleted $event): void
    {
        $customerId = $event->customerId();

        // Find pending/under_review erasure DSR for this customer
        $dsrs = $this->dsrRepository->findByCustomerId($customerId);

        $erasureDsr = null;
        foreach ($dsrs as $dsr) {
            if ($dsr->requestType()->equals(RequestType::erasure())
                && !$dsr->status()->isFinal()) {
                $erasureDsr = $dsr;

                break;
            }
        }

        if (null === $erasureDsr) {
            $this->logger->debug('No pending Privacy erasure DSR found for completed deletion', [
                'customerId' => $customerId->toString(),
            ]);

            return;
        }

        // Transition DSR to completed
        if ($erasureDsr->status()->equals(RequestStatus::pending())) {
            $erasureDsr->startReview('Auto-completed via Customer anonymization');
        }

        $erasureDsr->complete();
        $this->dsrRepository->save($erasureDsr);

        $this->logger->info('Privacy erasure DSR completed after Customer deletion', [
            'dsrId' => $erasureDsr->id()->toString(),
            'customerId' => $customerId->toString(),
        ]);
    }
}
