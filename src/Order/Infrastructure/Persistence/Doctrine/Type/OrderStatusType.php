<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Type;

use App\Order\Domain\Model\OrderStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class OrderStatusType extends Type
{
    public const NAME = 'order_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?OrderStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return OrderStatus::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof OrderStatus) {
            return $value->value();
        }

        return $value;
    }
}
