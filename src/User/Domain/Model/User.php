<?php

declare(strict_types=1);

namespace App\User\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Event\UserAccountLocked;
use App\User\Domain\Event\UserAccountUnlocked;
use App\User\Domain\Event\UserCreated;
use App\User\Domain\Event\UserEmailVerified;
use App\User\Domain\Event\UserPasswordChanged;
use App\User\Domain\Event\UserRoleAdded;
use App\User\Domain\Event\UserRoleRemoved;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;

final class User extends AggregateRoot
{
    private UserId $id;
    private Email $email;
    private Username $username;
    private HashedPassword $password;
    /** @var list<UserRole> */
    private array $roles;
    private \DateTimeImmutable $createdAt;
    private bool $emailVerified;
    private ?\DateTimeImmutable $emailVerifiedAt;
    private bool $isLocked;
    private ?string $lockReason;
    private ?\DateTimeImmutable $lockedAt;

    private function __construct(
        UserId $id,
        Email $email,
        Username $username,
        HashedPassword $password,
        array $roles,
        \DateTimeImmutable $createdAt,
        bool $emailVerified = false,
        ?\DateTimeImmutable $emailVerifiedAt = null,
        bool $isLocked = false,
        ?string $lockReason = null,
        ?\DateTimeImmutable $lockedAt = null
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->username = $username;
        $this->password = $password;
        $this->roles = $roles;
        $this->createdAt = $createdAt;
        $this->emailVerified = $emailVerified;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->isLocked = $isLocked;
        $this->lockReason = $lockReason;
        $this->lockedAt = $lockedAt;
    }

    public static function create(
        Email $email,
        Username $username,
        HashedPassword $password,
        array $roles = []
    ): self {
        $userId = UserId::generate();
        $createdAt = new \DateTimeImmutable();

        // Ensure at least ROLE_USER
        if (empty($roles)) {
            $roles = [UserRole::user()];
        }

        $user = new self(
            $userId,
            $email,
            $username,
            $password,
            $roles,
            $createdAt
        );

        $user->recordEvent(new UserCreated(
            $userId,
            $email->toString(),
            $username->toString(),
            $createdAt
        ));

        return $user;
    }

    public static function reconstitute(
        UserId $id,
        Email $email,
        Username $username,
        HashedPassword $password,
        array $roles,
        \DateTimeImmutable $createdAt,
        bool $emailVerified = false,
        ?\DateTimeImmutable $emailVerifiedAt = null,
        bool $isLocked = false,
        ?string $lockReason = null,
        ?\DateTimeImmutable $lockedAt = null
    ): self {
        return new self(
            $id,
            $email,
            $username,
            $password,
            $roles,
            $createdAt,
            $emailVerified,
            $emailVerifiedAt,
            $isLocked,
            $lockReason,
            $lockedAt
        );
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    /**
     * @return list<UserRole>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * @return list<string>
     */
    public function rolesAsStrings(): array
    {
        return array_map(fn (UserRole $role) => $role->toString(), $this->roles);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function hasRole(UserRole $role): bool
    {
        foreach ($this->roles as $userRole) {
            if ($userRole->equals($role)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::superAdmin());
    }

    public function emailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function emailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function lockReason(): ?string
    {
        return $this->lockReason;
    }

    public function lockedAt(): ?\DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function changePassword(HashedPassword $newPassword): void
    {
        if ($this->isLocked) {
            throw new \DomainException('Cannot change password for locked account');
        }

        $this->password = $newPassword;
        $this->recordEvent(new UserPasswordChanged(
            $this->id,
            new \DateTimeImmutable()
        ));
    }

    public function addRole(UserRole $role): void
    {
        if ($this->hasRole($role)) {
            throw new \DomainException('User already has this role');
        }

        $this->roles[] = $role;
        $this->recordEvent(new UserRoleAdded(
            $this->id,
            $role,
            new \DateTimeImmutable()
        ));
    }

    public function removeRole(UserRole $role): void
    {
        if (!$this->hasRole($role)) {
            throw new \DomainException('User does not have this role');
        }

        // Cannot remove ROLE_USER if it's the only role
        if ($role->equals(UserRole::user()) && 1 === count($this->roles)) {
            throw new \DomainException('Cannot remove ROLE_USER when it is the only role');
        }

        $this->roles = array_values(array_filter(
            $this->roles,
            fn (UserRole $userRole) => !$userRole->equals($role)
        ));

        $this->recordEvent(new UserRoleRemoved(
            $this->id,
            $role,
            new \DateTimeImmutable()
        ));
    }

    public function verifyEmail(): void
    {
        if ($this->emailVerified) {
            throw new \DomainException('Email is already verified');
        }

        $this->emailVerified = true;
        $this->emailVerifiedAt = new \DateTimeImmutable();

        $this->recordEvent(new UserEmailVerified(
            $this->id,
            $this->emailVerifiedAt
        ));
    }

    public function lock(string $reason): void
    {
        if ($this->isLocked) {
            throw new \DomainException('Account is already locked');
        }

        if ('' === trim($reason) || '0' === trim($reason)) {
            throw new \DomainException('Lock reason cannot be empty');
        }

        $this->isLocked = true;
        $this->lockReason = $reason;
        $this->lockedAt = new \DateTimeImmutable();

        $this->recordEvent(new UserAccountLocked(
            $this->id,
            $reason,
            $this->lockedAt
        ));
    }

    public function unlock(): void
    {
        if (!$this->isLocked) {
            throw new \DomainException('Account is not locked');
        }

        $this->isLocked = false;
        $this->lockReason = null;
        $this->lockedAt = null;

        $this->recordEvent(new UserAccountUnlocked(
            $this->id,
            new \DateTimeImmutable()
        ));
    }
}
