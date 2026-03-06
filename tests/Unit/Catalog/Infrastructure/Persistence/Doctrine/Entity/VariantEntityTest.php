<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\VariantEntity;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class VariantEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $variant = Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('CLO-NIK-000001-red-xl'),
            ['color' => 'red', 'size' => 'xl'],
            Money::of('29.99', 'EUR'),
            Stock::create(50),
        );

        $entity = VariantEntity::fromDomainModel($variant);

        self::assertSame('CLO-NIK-000001-red-xl', $entity->getSku());
        self::assertSame(['color' => 'red', 'size' => 'xl'], $entity->getOptionValueMap());
        self::assertTrue($entity->isActive());

        $restored = $entity->toDomainModel();
        self::assertSame('CLO-NIK-000001-red-xl', $restored->getSku()->value());
        self::assertTrue($restored->isActive());
    }
}
