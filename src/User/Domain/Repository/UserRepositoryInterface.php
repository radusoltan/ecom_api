<?php

declare(strict_types=1);

namespace App\User\Domain\Repository;

use App\Shared\Domain\ValueObject\Email;
use App\User\Domain\Model\User;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function delete(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findByUsername(Username $username): ?User;

    /**
     * @return list<User>
     */
    public function findAll(): array;
}
