<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Domain-specific InvalidArgumentException.
 *
 * Used when domain validation fails with invalid arguments.
 */
class InvalidArgumentException extends \InvalidArgumentException
{
}
