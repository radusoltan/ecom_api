<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Persistence\Doctrine\Type;

use App\Invoice\Domain\Model\InvoiceNumber;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class InvoiceNumberType extends Type
{
    public const NAME = 'invoice_number';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?InvoiceNumber
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return InvoiceNumber::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof InvoiceNumber) {
            return $value->toString();
        }

        return $value;
    }

}
