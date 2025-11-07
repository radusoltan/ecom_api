<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Repository;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
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
        } else {
            $entity = CustomerEntity::fromDomainModel($customer);
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
            'email' => $email->toString(),
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
