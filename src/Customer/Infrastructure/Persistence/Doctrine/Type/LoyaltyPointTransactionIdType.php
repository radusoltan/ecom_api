<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\LoyaltyPointTransactionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class LoyaltyPointTransactionIdType extends Type
{
    public const NAME = 'loyalty_point_transaction_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LoyaltyPointTransactionId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return LoyaltyPointTransactionId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof LoyaltyPointTransactionId) {
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
