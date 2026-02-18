<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\SetDefaultAddress;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetDefaultAddressCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(SetDefaultAddressCommand $command): void
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);
        $addressId = CustomerAddressId::fromString($command->addressId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $command->customerId));
        }

        $type = strtolower(trim($command->type));

        if ('shipping' === $type) {
            $customer->setDefaultShippingAddress($addressId);
        } elseif ('billing' === $type) {
            $customer->setDefaultBillingAddress($addressId);
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid address type for default: "%s". Allowed: shipping, billing', $command->type));
        }

        $this->customerRepository->save($customer);
    }
}
