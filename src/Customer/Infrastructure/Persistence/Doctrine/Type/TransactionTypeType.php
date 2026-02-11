<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\TransactionType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TransactionTypeType extends Type
{
    public const NAME = 'transaction_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TransactionType
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return TransactionType::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TransactionType) {
            return $value->value;
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
