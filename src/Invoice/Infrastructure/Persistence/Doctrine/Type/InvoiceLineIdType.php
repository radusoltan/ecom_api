<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Persistence\Doctrine\Type;

use App\Invoice\Domain\Model\InvoiceLineId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class InvoiceLineIdType extends Type
{
    public const NAME = 'invoice_line_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?InvoiceLineId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return InvoiceLineId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof InvoiceLineId) {
            return $value->toString();
        }

        return $value;
    }
}
