<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetAddressById;

use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerAddressEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Address By ID Query Handler.
 *
 * Retrieves a single address by its ID.
 */
#[AsMessageHandler]
final readonly class GetAddressByIdHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(GetAddressById $query): ?CustomerAddressDTO
    {
        $addressRepository = $this->entityManager->getRepository(CustomerAddressEntity::class);

        $address = $addressRepository->find($query->addressId);
        if (null === $address) {
            return null;
        }

        // Verify customer and tenant match
        if ($address->getCustomerId() !== $query->customerId->toString()) {
            throw new \DomainException('Address does not belong to this customer');
        }

        if ($address->getTenantId() !== $query->tenantId->toString()) {
            throw new \DomainException('Address does not belong to this tenant');
        }

        // Don't return deleted addresses
        if ($address->isDeleted()) {
            return null;
        }

        return CustomerAddressDTO::fromEntity($address);
    }
}
