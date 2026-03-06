<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Type;

use App\Order\Domain\Model\OrderId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class OrderIdType extends Type
{
    public const NAME = 'order_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?OrderId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return OrderId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof OrderId) {
            return $value->toString();
        }

        return $value;
    }
}
