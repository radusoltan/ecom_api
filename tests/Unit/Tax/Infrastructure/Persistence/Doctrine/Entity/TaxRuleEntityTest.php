<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use PHPUnit\Framework\TestCase;

final class TaxRuleEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $taxRule = TaxRule::create(
            TaxRuleId::generate(),
            TenantId::generate(),
            TaxJurisdiction::fromCountryAndRegion('DE', 'BY'),
            TaxCategory::STANDARD,
            TaxRate::fromPercentage(19.0),
            'German VAT Standard',
            'Standard VAT rate for Germany',
            10,
            true,
            new \DateTimeImmutable('-1 year'),
            new \DateTimeImmutable('+1 year'),
            false,
        );

        $entity = TaxRuleEntity::fromDomainModel($taxRule);

        self::assertSame($taxRule->id()->toString(), $entity->getId());
        self::assertSame('DE', $entity->getCountryCode());
        self::assertSame('BY', $entity->getRegionCode());
        self::assertSame('standard', $entity->getCategory());
        self::assertSame('19', $entity->getRate());
        self::assertSame('German VAT Standard', $entity->getName());
        self::assertSame('Standard VAT rate for Germany', $entity->getDescription());
        self::assertTrue($entity->isActive());
        self::assertSame(10, $entity->getPriority());
        self::assertFalse($entity->isReverseCharge());
        self::assertNotNull($entity->getValidFrom());
        self::assertNotNull($entity->getValidUntil());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($taxRule->id()));
        self::assertSame('German VAT Standard', $restored->name());
    }

    public function testUpdateFromDomainModel(): void
    {
        $taxRule = TaxRule::create(
            TaxRuleId::generate(),
            TenantId::generate(),
            TaxJurisdiction::fromCountryCode('FR'),
            TaxCategory::STANDARD,
            TaxRate::fromPercentage(20.0),
            'French VAT',
        );

        $entity = TaxRuleEntity::fromDomainModel($taxRule);
        $taxRule->deactivate();
        $entity->updateFromDomainModel($taxRule);

        self::assertFalse($entity->isActive());
    }

    public function testSetters(): void
    {
        $entity = new TaxRuleEntity();
        $entity->setTenantId('tenant-001');
        $entity->setCountryCode('US');
        $entity->setRegionCode('CA');
        $entity->setCategory('reduced');
        $entity->setRate('8.25');
        $entity->setName('CA Sales Tax');
        $entity->setDescription('California sales tax');
        $entity->setIsActive(true);
        $entity->setPriority(5);
        $entity->setIsReverseCharge(true);
        $entity->setValidFrom(new \DateTimeImmutable());
        $entity->setValidUntil(new \DateTimeImmutable('+1 year'));

        self::assertSame('tenant-001', $entity->getTenantId());
        self::assertSame('US', $entity->getCountryCode());
        self::assertSame('CA', $entity->getRegionCode());
        self::assertSame('reduced', $entity->getCategory());
        self::assertSame('8.25', $entity->getRate());
        self::assertSame('CA Sales Tax', $entity->getName());
        self::assertSame('California sales tax', $entity->getDescription());
        self::assertTrue($entity->isActive());
        self::assertSame(5, $entity->getPriority());
        self::assertTrue($entity->isReverseCharge());
    }
}
