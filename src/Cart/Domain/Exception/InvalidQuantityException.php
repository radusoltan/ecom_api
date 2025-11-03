<?php

declare(strict_types=1);

namespace App\Cart\Domain\Exception;

use InvalidArgumentException;

final class InvalidQuantityException extends InvalidArgumentException
{
}