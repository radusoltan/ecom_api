<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RequestDataExport;

use App\Customer\Application\Message\GenerateDataExportMessage;
use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\DataExportRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Request Data Export Command Handler.
 *
 * Creates a pending export request and dispatches async message for processing.
 */
#[AsMessageHandler]
final readonly class RequestDataExportCommandHandler
{
    private const RATE_LIMIT_HOURS = 24;

    public function __construct(
        private DataExportRequestRepositoryInterface $exportRequestRepository,
        private CustomerRepositoryInterface $customerRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestDataExportCommand $command): string
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);

        // Verify customer exists
        $customer = $this->customerRepository->findById($customerId, $tenantId);
        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer %s not found', $customerId->toString()));
        }

        // Check for pending requests
        $pendingRequest = $this->exportRequestRepository->findPendingByCustomerId($customerId, $tenantId);
        if (null !== $pendingRequest) {
            throw new \DomainException('A data export request is already pending for this customer');
        }

        // Check rate limit (1 per 24 hours)
        $since = new \DateTimeImmutable(sprintf('-%d hours', self::RATE_LIMIT_HOURS));
        $recentCount = $this->exportRequestRepository->countRecentByCustomerId($customerId, $since);

        if ($recentCount > 0) {
            throw new \DomainException(sprintf('Rate limit exceeded. Only 1 export request allowed per %d hours', self::RATE_LIMIT_HOURS));
        }

        // Create pending request
        $request = DataExportRequest::create($customerId, $tenantId);
        $this->exportRequestRepository->save($request);

        $this->logger->info('Data export requested', [
            'request_id' => $request->id()->toString(),
            'customer_id' => $customerId->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);

        // Dispatch async message for processing
        $this->messageBus->dispatch(new GenerateDataExportMessage(
            $request->id()->toString(),
            $customerId->toString(),
            $tenantId->toString()
        ));

        return $request->id()->toString();
    }
}
