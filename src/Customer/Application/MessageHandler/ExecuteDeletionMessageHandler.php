<?php

declare(strict_types=1);

namespace App\Customer\Application\MessageHandler;

use App\Customer\Application\Message\ExecuteDeletionMessage;
use App\Customer\Application\Service\CustomerAnonymizationService;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Execute Deletion Message Handler.
 *
 * Processes account deletion requests by:
 * 1. Loading the deletion request
 * 2. Checking if it can be executed
 * 3. Anonymizing customer data
 * 4. Deleting personal data
 * 5. Marking request as completed
 */
#[AsMessageHandler]
final readonly class ExecuteDeletionMessageHandler
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerAnonymizationService $anonymizationService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ExecuteDeletionMessage $message): void
    {
        // Note: We don't have tenant context in the message, so we need to find the request first
        $entity = $this->entityManager
            ->getRepository(\App\Customer\Infrastructure\Persistence\Doctrine\Entity\DeletionRequestEntity::class)
            ->find($message->requestId->toString());

        if (null === $entity) {
            $this->logger->warning('Deletion request not found', [
                'requestId' => $message->requestId->toString(),
            ]);
            return;
        }

        $request = $entity->toDomainModel();

        // Check if request can be executed
        if (!$request->canBeExecuted()) {
            $this->logger->info('Deletion request cannot be executed yet', [
                'requestId' => $request->id()->toString(),
                'status' => $request->status()->value,
                'scheduledFor' => $request->scheduledFor()->format('Y-m-d H:i:s'),
                'isOnHold' => $request->isOnHold(),
            ]);
            return;
        }

        try {
            // Mark as processing
            $request->process();
            $this->deletionRequestRepository->save($request);

            // Load customer
            $customer = $this->customerRepository->findById(
                $request->customerId(),
                $request->tenantId()
            );

            if (null === $customer) {
                throw new \RuntimeException('Customer not found');
            }

            // Anonymize customer data
            $this->anonymizationService->anonymize($customer);

            // Generate anonymized email
            $anonymizedEmail = $this->anonymizationService->generateAnonymizedEmail(
                $customer->id()->toString()
            );

            // Save anonymized customer
            $this->customerRepository->save($customer);

            // Update email at entity level (email change not supported in aggregate)
            $customerEntity = $this->entityManager
                ->getRepository(CustomerEntity::class)
                ->findOneBy([
                    'id' => $customer->id()->toString(),
                    'tenantId' => $customer->tenantId()->toString(),
                ]);

            if (null !== $customerEntity) {
                $customerEntity->setEmail($anonymizedEmail->toString());
                $this->entityManager->flush();
            }

            // Delete personal data (consent history, export requests, etc.)
            $this->anonymizationService->deletePersonalData($customer, $request->tenantId());

            // Mark request as completed
            $request->complete();
            $this->deletionRequestRepository->save($request);

            $this->logger->info('Account deletion completed successfully', [
                'requestId' => $request->id()->toString(),
                'customerId' => $request->customerId()->toString(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to execute account deletion', [
                'requestId' => $request->id()->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
