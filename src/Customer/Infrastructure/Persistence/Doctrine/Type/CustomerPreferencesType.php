<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\CustomerPreferences;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type for CustomerPreferences Value Object.
 *
 * Converts between CustomerPreferences object and JSON in database.
 */
final class CustomerPreferencesType extends Type
{
    private const NAME = 'customer_preferences';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): CustomerPreferences
    {
        if (null === $value || '' === $value) {
            // Return default preferences if null
            return CustomerPreferences::create();
        }

        if ($value instanceof CustomerPreferences) {
            return $value;
        }

        // Decode JSON to array
        $data = json_decode((string) $value, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON for CustomerPreferences');
        }

        return CustomerPreferences::fromArray($data);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        if (null === $value) {
            // Store default preferences as JSON
            $encoded = json_encode(CustomerPreferences::create()->toArray());
            if (false === $encoded) {
                throw new \RuntimeException('Failed to encode default preferences to JSON');
            }

            return $encoded;
        }

        if ($value instanceof CustomerPreferences) {
            $encoded = json_encode($value->toArray());
            if (false === $encoded) {
                throw new \RuntimeException('Failed to encode preferences to JSON');
            }

            return $encoded;
        }

        throw new \InvalidArgumentException('Expected CustomerPreferences instance or null');
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
