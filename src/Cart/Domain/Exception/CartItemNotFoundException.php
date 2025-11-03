<?php

declare(strict_types=1);

namespace App\Cart\Domain\Exception;

use RuntimeException;

final class CartItemNotFoundException extends RuntimeException
{
}