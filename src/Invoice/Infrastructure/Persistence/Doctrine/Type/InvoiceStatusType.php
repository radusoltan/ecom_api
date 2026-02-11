<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Persistence\Doctrine\Type;

use App\Invoice\Domain\Model\InvoiceStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class InvoiceStatusType extends Type
{
    public const NAME = 'invoice_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?InvoiceStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return InvoiceStatus::from($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof InvoiceStatus) {
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
