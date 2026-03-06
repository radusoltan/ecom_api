<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Infrastructure\Persistence\Doctrine\Entity\InvoiceLineEntity;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class InvoiceLineEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $productId = ProductId::generate();
        $line = InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget Pro 2000',
            3,
            Money::fromScalars(2500, 'EUR'),
            19.0,
            $productId,
            'PRD-000001',
            1,
        );

        $entity = InvoiceLineEntity::fromDomainModel($line, 'invoice-001');

        self::assertSame($line->id()->toString(), $entity->getId());
        self::assertSame($productId->toString(), $entity->getProductId());
        self::assertSame('PRD-000001', $entity->getSku());
        self::assertSame('Widget Pro 2000', $entity->getDescription());
        self::assertSame(3, $entity->getQuantity());
        self::assertSame(2500, $entity->getUnitPriceAmount());
        self::assertSame('EUR', $entity->getUnitPriceCurrency());
        self::assertSame(19.0, $entity->getTaxRate());
        self::assertNotNull($entity->getTaxAmount());
        self::assertSame('EUR', $entity->getTaxCurrency());
        self::assertNotNull($entity->getLineTotalAmount());
        self::assertSame('EUR', $entity->getLineTotalCurrency());
        self::assertSame(1, $entity->getPosition());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertSame('Widget Pro 2000', $restored->description());
        self::assertSame(3, $restored->quantity());
    }
}
