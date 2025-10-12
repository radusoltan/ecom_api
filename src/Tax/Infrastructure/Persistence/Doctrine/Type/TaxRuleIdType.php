<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Type;

use App\Tax\Domain\ValueObject\TaxRuleId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type for TaxRuleId
 */
final class TaxRuleIdType extends Type
{
    public const NAME = 'tax_rule_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaxRuleId
    {
        if ($value === null || $value instanceof TaxRuleId) {
            return $value;
        }

        return TaxRuleId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TaxRuleId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected TaxRuleId instance');
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
