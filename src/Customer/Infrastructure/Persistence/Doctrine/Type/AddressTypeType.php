<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Custom Doctrine type for AddressType.
 *
 * Stores address type as VARCHAR(20) in database.
 * Valid values: 'billing', 'shipping'
 */
final class AddressTypeType extends StringType
{
    public const NAME = 'address_type';

    private const VALID_TYPES = ['billing', 'shipping'];

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Expected string, got %s', get_debug_type($value)));
        }

        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid address type "%s". Allowed values: %s', $value, implode(', ', self::VALID_TYPES)));
        }

        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Expected string, got %s', get_debug_type($value)));
        }

        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid address type "%s". Allowed values: %s', $value, implode(', ', self::VALID_TYPES)));
        }

        return $value;
    }

}
