<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\User\Infrastructure\Persistence\Doctrine\Repository\DoctrineORMUserRepository;
use App\User\Presentation\Api\Processor\CreateUserProcessor;
use App\User\Presentation\Api\Processor\DeleteUserProcessor;
use App\User\Presentation\Api\Processor\UpdateUserProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DoctrineORMUserRepository::class)]
#[ORM\Table(name: 'users')]
#[ApiResource(
    shortName: 'User',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['user:read', 'user:list']],
            security: "is_granted('user.view')"
        ),
        new Post(
            processor: CreateUserProcessor::class,
            denormalizationContext: ['groups' => ['user:create']],
            normalizationContext: ['groups' => ['user:read']],
            security: "is_granted('user.create')"
        ),
        new Get(
            normalizationContext: ['groups' => ['user:read', 'user:detail']],
            security: "is_granted('user.view', object)"
        ),
        new Patch(
            processor: UpdateUserProcessor::class,
            denormalizationContext: ['groups' => ['user:update']],
            normalizationContext: ['groups' => ['user:read']],
            security: "is_granted('user.edit', object)"
        ),
        new Delete(
            processor: DeleteUserProcessor::class,
            security: "is_granted('user.delete', object)"
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    paginationItemsPerPage: 25
)]
class UserEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['user:read'])]
    private string $id;

    #[ORM\Column(type: 'encrypted_string')]
    #[Groups(['user:read', 'user:create'])]
    private string $email;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $emailBlindIndex = null;

    #[ORM\Column(type: 'encrypted_string')]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private string $username;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $usernameBlindIndex = null;

    #[ORM\Column(type: 'string')]
    #[Groups(['user:create'])]
    private string $password;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private array $roles = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $mfaEnabled = false;

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $totpSecret = null;

    /** @var list<string>|null */
    #[ORM\Column(type: 'encrypted_json', nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $mfaEnabledAt = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getEmailBlindIndex(): ?string
    {
        return $this->emailBlindIndex;
    }

    public function setEmailBlindIndex(?string $emailBlindIndex): self
    {
        $this->emailBlindIndex = $emailBlindIndex;

        return $this;
    }

    public function getUsernameBlindIndex(): ?string
    {
        return $this->usernameBlindIndex;
    }

    public function setUsernameBlindIndex(?string $usernameBlindIndex): self
    {
        $this->usernameBlindIndex = $usernameBlindIndex;

        return $this;
    }

    public function isMfaEnabled(): bool
    {
        return $this->mfaEnabled;
    }

    public function setMfaEnabled(bool $mfaEnabled): self
    {
        $this->mfaEnabled = $mfaEnabled;

        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    /**
     * @param list<string>|null $backupCodes
     */
    public function setBackupCodes(?array $backupCodes): self
    {
        $this->backupCodes = $backupCodes;

        return $this;
    }

    public function getMfaEnabledAt(): ?\DateTimeImmutable
    {
        return $this->mfaEnabledAt;
    }

    public function setMfaEnabledAt(?\DateTimeImmutable $mfaEnabledAt): self
    {
        $this->mfaEnabledAt = $mfaEnabledAt;

        return $this;
    }
}
