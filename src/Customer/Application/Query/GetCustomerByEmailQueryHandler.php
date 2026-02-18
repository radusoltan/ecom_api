<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCustomerByEmailQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(GetCustomerByEmailQuery $query): ?CustomerDTO
    {
        $email = Email::fromString($query->email);
        $tenantId = TenantId::fromString($query->tenantId);

        $customer = $this->customerRepository->findByEmail($email, $tenantId);

        if (null === $customer) {
            return null;
        }

        return CustomerDTO::fromDomain($customer);
    }
}
