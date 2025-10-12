<?php

declare(strict_types=1);

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeactivateCustomerCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function __invoke(DeactivateCustomerCommand $command): void
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if ($customer === null) {
            throw new \InvalidArgumentException(
                sprintf('Customer with ID "%s" not found', $command->customerId)
            );
        }

        $customer->deactivate();

        $this->customerRepository->save($customer);
    }
}
