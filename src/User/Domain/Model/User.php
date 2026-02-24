<?php

declare(strict_types=1);

namespace App\User\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Event\UserAccountLocked;
use App\User\Domain\Event\UserAccountUnlocked;
use App\User\Domain\Event\MfaBackupCodeConsumed;
use App\User\Domain\Event\MfaBackupCodesRegenerated;
use App\User\Domain\Event\MfaDisabled;
use App\User\Domain\Event\MfaEnabled;
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
    private bool $mfaEnabled;
    private ?string $totpSecret;
    /** @var list<string> Bcrypt-hashed backup codes */
    private array $backupCodes;
    private ?\DateTimeImmutable $mfaEnabledAt;

    /**
     * @param list<string> $backupCodes
     */
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
        ?\DateTimeImmutable $lockedAt = null,
        bool $mfaEnabled = false,
        ?string $totpSecret = null,
        array $backupCodes = [],
        ?\DateTimeImmutable $mfaEnabledAt = null,
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
        $this->mfaEnabled = $mfaEnabled;
        $this->totpSecret = $totpSecret;
        $this->backupCodes = $backupCodes;
        $this->mfaEnabledAt = $mfaEnabledAt;
    }

    public static function create(
        Email $email,
        Username $username,
        HashedPassword $password,
        array $roles = [],
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

    /**
     * @param list<string> $backupCodes
     */
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
        ?\DateTimeImmutable $lockedAt = null,
        bool $mfaEnabled = false,
        ?string $totpSecret = null,
        array $backupCodes = [],
        ?\DateTimeImmutable $mfaEnabledAt = null,
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
            $lockedAt,
            $mfaEnabled,
            $totpSecret,
            $backupCodes,
            $mfaEnabledAt
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

    // MFA methods

    public function mfaEnabled(): bool
    {
        return $this->mfaEnabled;
    }

    public function totpSecret(): ?string
    {
        return $this->totpSecret;
    }

    /**
     * @return list<string>
     */
    public function backupCodes(): array
    {
        return $this->backupCodes;
    }

    public function mfaEnabledAt(): ?\DateTimeImmutable
    {
        return $this->mfaEnabledAt;
    }

    /**
     * @param list<string> $hashedBackupCodes Bcrypt-hashed backup codes
     */
    public function enableMfa(string $totpSecret, array $hashedBackupCodes): void
    {
        if ($this->mfaEnabled) {
            throw new \DomainException('MFA is already enabled');
        }

        $this->mfaEnabled = true;
        $this->totpSecret = $totpSecret;
        $this->backupCodes = $hashedBackupCodes;
        $this->mfaEnabledAt = new \DateTimeImmutable();

        $this->recordEvent(new MfaEnabled(
            $this->id,
            $this->mfaEnabledAt
        ));
    }

    public function disableMfa(): void
    {
        if (!$this->mfaEnabled) {
            throw new \DomainException('MFA is not enabled');
        }

        $this->mfaEnabled = false;
        $this->totpSecret = null;
        $this->backupCodes = [];
        $this->mfaEnabledAt = null;

        $this->recordEvent(new MfaDisabled(
            $this->id,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Consume a backup code and return true if it was valid.
     */
    public function consumeBackupCode(string $hashedCode): bool
    {
        $index = array_search($hashedCode, $this->backupCodes, true);

        if (false === $index) {
            return false;
        }

        array_splice($this->backupCodes, $index, 1);

        $this->recordEvent(new MfaBackupCodeConsumed(
            $this->id,
            count($this->backupCodes),
            new \DateTimeImmutable()
        ));

        return true;
    }

    /**
     * @param list<string> $hashedBackupCodes
     */
    public function regenerateBackupCodes(array $hashedBackupCodes): void
    {
        if (!$this->mfaEnabled) {
            throw new \DomainException('Cannot regenerate backup codes when MFA is disabled');
        }

        $this->backupCodes = $hashedBackupCodes;

        $this->recordEvent(new MfaBackupCodesRegenerated(
            $this->id,
            new \DateTimeImmutable()
        ));
    }
}
