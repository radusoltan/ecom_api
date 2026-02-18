<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\CustomerSegment;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class CustomerSegmentType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?CustomerSegment
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof CustomerSegment) {
            return $value;
        }

        return CustomerSegment::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CustomerSegment) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected CustomerSegment instance');
    }

}
