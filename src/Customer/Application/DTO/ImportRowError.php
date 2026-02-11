<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Represents a validation error for a specific row in an import file.
 */
final readonly class ImportRowError
{
    public function __construct(
        public int $row,
        public string $field,
        public string $message,
        public mixed $value
    ) {
    }

    public function toString(): string
    {
        return sprintf(
            'Row %d, Field "%s": %s (value: %s)',
            $this->row,
            $this->field,
            $this->message,
            $this->formatValue()
        );
    }

    private function formatValue(): string
    {
        if (null === $this->value) {
            return 'null';
        }

        if (is_array($this->value)) {
            $encoded = json_encode($this->value);

            return false !== $encoded ? $encoded : 'array';
        }

        if (is_bool($this->value)) {
            return $this->value ? 'true' : 'false';
        }

        return (string) $this->value;
    }
}
