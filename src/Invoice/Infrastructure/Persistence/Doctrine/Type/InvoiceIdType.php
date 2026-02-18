<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Persistence\Doctrine\Type;

use App\Invoice\Domain\Model\InvoiceId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class InvoiceIdType extends Type
{
    public const NAME = 'invoice_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?InvoiceId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return InvoiceId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof InvoiceId) {
            return $value->toString();
        }

        return $value;
    }

}
