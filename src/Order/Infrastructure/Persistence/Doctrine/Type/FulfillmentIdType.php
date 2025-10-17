<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Type;

use App\Order\Domain\ValueObject\FulfillmentId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class FulfillmentIdType extends Type
{
    public const NAME = 'fulfillment_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 26]); // ULID length
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?FulfillmentId
    {
        if ($value === null || $value === '') {
            return null;
        }

        return FulfillmentId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof FulfillmentId) {
            return $value->toString();
        }

        return $value;
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
