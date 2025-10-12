<?php

declare(strict_types=1);

namespace App\Tenant\Domain\ValueObject;

use Stringable;
use InvalidArgumentException;

final readonly class TenantName implements Stringable
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 100;

    private string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '' || $trimmed === '0') {
            throw new InvalidArgumentException('Tenant name cannot be empty');
        }

        if (strlen($trimmed) < self::MIN_LENGTH) {
            throw new InvalidArgumentException(sprintf('Tenant name must be at least %d characters long', self::MIN_LENGTH));
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('Tenant name cannot exceed %d characters', self::MAX_LENGTH));
        }

        // Validate alphanumeric + spaces only
        if (!preg_match('/^[a-zA-Z0-9\s]+$/', $trimmed)) {
            throw new InvalidArgumentException('Tenant name can only contain alphanumeric characters and spaces');
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(TenantName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
