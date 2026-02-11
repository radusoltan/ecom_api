<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateAddress;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAddressCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function __invoke(UpdateAddressCommand $command): void
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);
        $addressId = CustomerAddressId::fromString($command->addressId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $command->customerId));
        }

        $addressType = AddressType::fromString($command->type);

        $updatedAddress = CustomerAddress::create(
            id: $addressId,
            street: $command->street,
            street2: $command->street2,
            city: $command->city,
            state: $command->state,
            postalCode: $command->postalCode,
            country: $command->country,
            type: $addressType,
            isDefaultShipping: $command->isDefaultShipping,
            isDefaultBilling: $command->isDefaultBilling
        );

        $customer->updateAddress($addressId, $updatedAddress);

        $this->customerRepository->save($customer);
    }
}
