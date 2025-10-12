<?php

declare(strict_types=1);

namespace App\Customer\Application\Command;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterCustomerCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function __invoke(RegisterCustomerCommand $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $email = Email::fromString($command->email);

        // Check if customer with same email already exists
        $existing = $this->customerRepository->findByEmail($email, $tenantId);
        if ($existing !== null) {
            throw new \InvalidArgumentException(
                sprintf('Customer with email "%s" already exists for this tenant', $command->email)
            );
        }

        $customer = Customer::register(
            CustomerId::fromString($command->customerId),
            $tenantId,
            $email,
            $command->firstName,
            $command->lastName,
            $command->phoneNumber
        );

        $this->customerRepository->save($customer);
    }
}
