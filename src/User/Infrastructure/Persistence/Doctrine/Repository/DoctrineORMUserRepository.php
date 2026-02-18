<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Encryption\BlindIndexService;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEntity>
 */
final class DoctrineORMUserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly BlindIndexService $blindIndexService,
    ) {
        parent::__construct($registry, UserEntity::class);
    }

    public function save(User $user): void
    {
        $entity = $this->find($user->id()->toString());

        if (null === $entity) {
            $entity = new UserEntity();
            $entity->setId($user->id()->toString());
            $entity->setCreatedAt($user->createdAt());
        }

        $entity->setEmail($user->email()->toString());
        $entity->setEmailBlindIndex($this->blindIndexService->generate($user->email()->toString()));
        $entity->setUsername($user->username()->toString());
        $entity->setUsernameBlindIndex($this->blindIndexService->generate($user->username()->toString()));
        $entity->setPassword($user->password()->toString());
        $entity->setRoles($user->rolesAsStrings());
        $entity->setMfaEnabled($user->mfaEnabled());
        $entity->setTotpSecret($user->totpSecret());
        $entity->setBackupCodes([] !== $user->backupCodes() ? $user->backupCodes() : null);
        $entity->setMfaEnabledAt($user->mfaEnabledAt());

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function delete(User $user): void
    {
        $entity = $this->find($user->id()->toString());

        if (null === $entity) {
            throw new \DomainException(sprintf('User with ID "%s" not found', $user->id()->toString()));
        }

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }

    public function findById(UserId $id): ?User
    {
        $entity = $this->find($id->toString());

        return $entity ? $this->toDomain($entity) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $entity = $this->findOneBy(['emailBlindIndex' => $this->blindIndexService->generate($email->toString())]);

        return $entity ? $this->toDomain($entity) : null;
    }

    public function findByUsername(Username $username): ?User
    {
        $entity = $this->findOneBy(['usernameBlindIndex' => $this->blindIndexService->generate($username->toString())]);

        return $entity ? $this->toDomain($entity) : null;
    }

    public function findAll(): array
    {
        $entities = $this->findBy([]);

        return array_map(fn (UserEntity $entity) => $this->toDomain($entity), $entities);
    }

    private function toDomain(UserEntity $entity): User
    {
        $roles = array_map(
            fn (string $role) => UserRole::fromString($role),
            array_filter($entity->getRoles(), fn (string $role) => 'ROLE_USER' !== $role)
        );

        return User::reconstitute(
            UserId::fromString($entity->getId()),
            Email::fromString($entity->getEmail()),
            Username::fromString($entity->getUsername()),
            HashedPassword::fromHash($entity->getPassword()),
            $roles,
            $entity->getCreatedAt(),
            mfaEnabled: $entity->isMfaEnabled(),
            totpSecret: $entity->getTotpSecret(),
            backupCodes: $entity->getBackupCodes() ?? [],
            mfaEnabledAt: $entity->getMfaEnabledAt()
        );
    }
}
