<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\ACL;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Port\PersonalDataContributorInterface;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Customer Personal Data Contributor.
 *
 * Provides customer profile data to the Privacy context's PersonalDataInventory
 * without requiring Privacy to directly import Customer repositories.
 */
final readonly class CustomerPersonalDataContributor implements PersonalDataContributorInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function contextName(): string
    {
        return 'customer';
    }

    public function collectPersonalData(array $subjectContext): array
    {
        $customerId = $subjectContext['customerId'] ?? null;
        $tenantId = $subjectContext['tenantId'] ?? null;

        if (null === $customerId || null === $tenantId) {
            return [];
        }

        $customer = $this->customerRepository->findById(
            CustomerId::fromString($customerId),
            TenantId::fromString($tenantId),
        );

        if (null === $customer) {
            return [];
        }

        return [
            'id' => $customer->id()->toString(),
            'email' => $customer->email()->toString(),
            'firstName' => $customer->firstName(),
            'lastName' => $customer->lastName(),
            'phoneNumber' => $customer->phoneNumber(),
            'segment' => $customer->segment()->value(),
            'loyaltyPoints' => $customer->loyaltyPoints(),
            'isActive' => $customer->isActive(),
            'addresses' => array_map(
                fn ($address) => $address->toArray(),
                $customer->getAddresses()
            ),
            'preferences' => $customer->preferences()->toArray(),
            'createdAt' => $customer->createdAt()->format('c'),
            'updatedAt' => $customer->updatedAt()->format('c'),
        ];
    }

    public function canDeleteData(array $subjectContext): bool
    {
        // Customer data can always be anonymized (not deleted)
        return true;
    }
}
