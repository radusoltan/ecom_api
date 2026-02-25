<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerAddressEntity;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Encryption\BlindIndexService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private BlindIndexService $blindIndexService,
    ) {
    }

    public function save(Customer $customer): void
    {
        $repository = $this->entityManager->getRepository(CustomerEntity::class);
        $existing = $repository->findOneBy([
            'id' => $customer->id()->toString(),
            'tenantId' => $customer->tenantId()->toString(),
        ]);

        if ($existing instanceof CustomerEntity) {
            $existing->updateFromDomainModel($customer);
            $existing->setEmailBlindIndex($this->blindIndexService->generate($customer->email()->toString()));
            $this->syncAddresses($existing, $customer);
        } else {
            $entity = CustomerEntity::fromDomainModel($customer);
            $entity->setEmailBlindIndex($this->blindIndexService->generate($customer->email()->toString()));
            $this->syncAddresses($entity, $customer);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($customer->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(CustomerId $id, TenantId $tenantId): ?Customer
    {
        $repository = $this->entityManager->getRepository(CustomerEntity::class);
        $entity = $repository->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        if (!$entity instanceof CustomerEntity) {
            return null;
        }

        // Refresh entity from database to avoid stale data from identity map
        // This ensures we always have the latest state, especially important
        // when multiple operations are performed on the same entity in tests
        $this->entityManager->refresh($entity);

        return $entity->toDomainModel();
    }

    public function findByEmail(Email $email, TenantId $tenantId): ?Customer
    {
        $repository = $this->entityManager->getRepository(CustomerEntity::class);
        $entity = $repository->findOneBy([
            'emailBlindIndex' => $this->blindIndexService->generate($email->toString()),
            'tenantId' => $tenantId->toString(),
        ]);

        if (!$entity instanceof CustomerEntity) {
            return null;
        }

        // Refresh entity from database to avoid stale data from identity map
        $this->entityManager->refresh($entity);

        return $entity->toDomainModel();
    }

    public function findAll(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(CustomerEntity::class);
        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn (CustomerEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function findBySegment(string $segment, TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(CustomerEntity::class);
        $entities = $repository->findBy([
            'segment' => $segment,
            'tenantId' => $tenantId->toString(),
        ]);

        return array_map(
            fn (CustomerEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    /**
     * Sync addresses from domain model to entity.
     */
    private function syncAddresses(CustomerEntity $entity, Customer $customer): void
    {
        $domainAddresses = $customer->getAddresses();
        $existingEntities = $entity->getAddressEntities();

        // Index existing address entities by ID
        $existingById = [];
        foreach ($existingEntities as $addressEntity) {
            $existingById[$addressEntity->getId()] = $addressEntity;
        }

        // Index domain addresses by ID
        $domainById = [];
        foreach ($domainAddresses as $address) {
            $domainById[$address->id->toString()] = $address;
        }

        // Add new addresses
        foreach ($domainAddresses as $address) {
            $addressId = $address->id->toString();
            if (!isset($existingById[$addressId])) {
                $addressEntity = CustomerAddressEntity::fromDomainModel(
                    $address->toArray(),
                    $customer->id()->toString(),
                    $customer->tenantId()->toString(),
                );
                $entity->addAddressEntity($addressEntity);
                $this->entityManager->persist($addressEntity);
            } else {
                // Update existing
                $existingById[$addressId]->updateFromDomainModel($address->toArray());
            }
        }

        // Remove addresses no longer in domain model
        foreach ($existingEntities->toArray() as $addressEntity) {
            if (!isset($domainById[$addressEntity->getId()])) {
                $entity->removeAddressEntity($addressEntity);
                $this->entityManager->remove($addressEntity);
            }
        }
    }

    public function delete(Customer $customer): void
    {
        $repository = $this->entityManager->getRepository(CustomerEntity::class);
        $entity = $repository->findOneBy([
            'id' => $customer->id()->toString(),
            'tenantId' => $customer->tenantId()->toString(),
        ]);

        if ($entity instanceof CustomerEntity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
