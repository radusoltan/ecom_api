<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Type;

use App\Order\Domain\ValueObject\FulfillmentStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class FulfillmentStatusType extends Type
{
    public const NAME = 'fulfillment_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?FulfillmentStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return FulfillmentStatus::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof FulfillmentStatus) {
            return $value->value();
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
