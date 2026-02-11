<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

final class UsernameAlreadyExistsException extends \DomainException
{
    public static function forUsername(string $username): self
    {
        return new self(sprintf('User with username "%s" already exists', $username));
    }
}
