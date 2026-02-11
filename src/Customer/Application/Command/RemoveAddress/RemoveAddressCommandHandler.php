<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\RemoveAddress;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveAddressCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function __invoke(RemoveAddressCommand $command): void
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);
        $addressId = CustomerAddressId::fromString($command->addressId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $command->customerId));
        }

        $customer->removeAddress($addressId);

        $this->customerRepository->save($customer);
    }
}
