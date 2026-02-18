<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Type;

use App\Tax\Domain\Model\TaxRuleId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type for TaxRuleId value object.
 *
 * Maps TaxRuleId UUID to database UUID column.
 */
final class TaxRuleIdType extends Type
{
    public const NAME = 'tax_rule_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaxRuleId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TaxRuleId) {
            return $value;
        }

        return TaxRuleId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TaxRuleId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected TaxRuleId instance');
    }

}
