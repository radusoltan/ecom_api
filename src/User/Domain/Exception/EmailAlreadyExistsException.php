<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

final class EmailAlreadyExistsException extends \DomainException
{
    public static function forEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" already exists', $email));
    }
}
