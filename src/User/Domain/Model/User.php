<?php

declare(strict_types=1);

namespace App\User\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Event\UserCreated;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\UserRole;
use App\User\Domain\ValueObject\Username;
use DateTimeImmutable;

final class User extends AggregateRoot
{
    private UserId $id;
    private Email $email;
    private Username $username;
    private HashedPassword $password;
    /** @var list<UserRole> */
    private array $roles;
    private DateTimeImmutable $createdAt;

    private function __construct(
        UserId $id,
        Email $email,
        Username $username,
        HashedPassword $password,
        array $roles,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->username = $username;
        $this->password = $password;
        $this->roles = $roles;
        $this->createdAt = $createdAt;
    }

    public static function create(
        Email $email,
        Username $username,
        HashedPassword $password,
        array $roles = []
    ): self {
        $userId = UserId::generate();
        $createdAt = new DateTimeImmutable();

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
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            $id,
            $email,
            $username,
            $password,
            $roles,
            $createdAt
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
        return array_map(fn(UserRole $role) => $role->toString(), $this->roles);
    }

    public function createdAt(): DateTimeImmutable
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
}
