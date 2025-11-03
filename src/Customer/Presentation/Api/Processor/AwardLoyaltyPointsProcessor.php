<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\AwardLoyaltyPointsCommand;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Customer\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMCustomerRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<CustomerEntity, CustomerEntity>
 */
final readonly class AwardLoyaltyPointsProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private DoctrineORMCustomerRepository $customerRepository
    ) {
    }

    /**
     * @param CustomerEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerEntity
    {
        $customerId = $uriVariables['id'];

        // Extract points, reason, and tenantId from the request
        $points = $context['request']?->request->get('points') ?? 0;
        $reason = $context['request']?->request->get('reason') ?? 'Manual award';

        // Get tenantId from the data object (CustomerEntity)
        $tenantId = $data->getTenantId();

        $command = new AwardLoyaltyPointsCommand(
            $customerId,
            $tenantId,
            (int) $points,
            (string) $reason
        );

        $this->commandBus->dispatch($command);

        // Fetch updated customer and return entity
        $customer = $this->customerRepository->findById(
            CustomerId::fromString($customerId),
            \App\Shared\Domain\ValueObject\TenantId::fromString($tenantId)
        );

        if ($customer === null) {
            throw new \RuntimeException(sprintf('Customer with ID "%s" not found', $customerId));
        }

        return CustomerEntity::fromDomainModel($customer);
    }
}
