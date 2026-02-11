<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

/**
 * Represents a single row from an import file.
 *
 * Generic DTO that can be used for both PriceList and Promotion imports
 */
final readonly class ImportRow
{
    /**
     * @param array<string, mixed> $data Raw data from the import row
     */
    public function __construct(
        public int $rowNumber,
        public array $data
    ) {
    }

    /**
     * Get a value from the row data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a key exists in the row data.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get string value or null.
     */
    public function getString(string $key): ?string
    {
        $value = $this->get($key);
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Get integer value or null.
     */
    public function getInt(string $key): ?int
    {
        $value = $this->get($key);
        if (null === $value || '' === $value) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Get float value or null.
     */
    public function getFloat(string $key): ?float
    {
        $value = $this->get($key);
        if (null === $value || '' === $value) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Get boolean value.
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if (null === $value || '' === $value) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        // Handle string representations
        $stringValue = strtolower((string) $value);
        return in_array($stringValue, ['true', '1', 'yes', 'on'], true);
    }
}
