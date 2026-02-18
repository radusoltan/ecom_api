<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\AddAddress;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddAddressCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(AddAddressCommand $command): string
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $command->customerId));
        }

        $addressId = CustomerAddressId::generate();
        $addressType = AddressType::fromString($command->type);

        $address = CustomerAddress::create(
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

        $customer->addAddress($address);

        $this->customerRepository->save($customer);

        return $addressId->toString();
    }
}
