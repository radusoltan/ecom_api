<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Alert Severity Value Object
 *
 * Defines severity levels for performance alerts
 */
final readonly class AlertSeverity
{
    private const INFO = 'info';
    private const WARNING = 'warning';
    private const ERROR = 'error';
    private const CRITICAL = 'critical';

    private const VALID_SEVERITIES = [
        self::INFO,
        self::WARNING,
        self::ERROR,
        self::CRITICAL,
    ];

    private function __construct(private string $value)
    {
        if (!in_array($value, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid alert severity: "%s". Valid severities: %s',
                    $value,
                    implode(', ', self::VALID_SEVERITIES)
                )
            );
        }
    }

    public static function info(): self
    {
        return new self(self::INFO);
    }

    public static function warning(): self
    {
        return new self(self::WARNING);
    }

    public static function error(): self
    {
        return new self(self::ERROR);
    }

    public static function critical(): self
    {
        return new self(self::CRITICAL);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isHigherThan(self $other): bool
    {
        $priority = [
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3,
            self::CRITICAL => 4,
        ];

        return $priority[$this->value] > $priority[$other->value];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
