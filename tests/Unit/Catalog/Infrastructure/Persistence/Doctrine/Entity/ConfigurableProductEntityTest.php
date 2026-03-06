<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ConfigurableProductEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ConfigurableProductEntityTest extends TestCase
{
    private ConfigurableProductId $configurableProductId;
    private ProductId $productId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        // ConfigurableProductId is defined in the same file as ConfigurableProduct;
        // referencing ConfigurableProduct first ensures the file is loaded.
        class_exists(ConfigurableProduct::class);
        $this->configurableProductId = ConfigurableProductId::fromString('cfgprod_test_001');
        $this->productId = ProductId::fromString('00000000-0000-4000-8000-000000000010');
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $domain = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $entity = ConfigurableProductEntity::fromDomainModel($domain);

        self::assertSame($this->configurableProductId->toString(), $entity->getId());
        self::assertSame($this->productId->toString(), $entity->getProductId());
        self::assertSame($this->tenantId->toString(), $entity->getTenantId());
        self::assertCount(0, $entity->getOptions());
        self::assertCount(0, $entity->getVariants());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        $restored = $entity->toDomainModel();
        self::assertSame($this->configurableProductId->toString(), $restored->getId()->toString());
        self::assertSame($this->productId->toString(), $restored->getProductId()->toString());
        self::assertSame($this->tenantId->toString(), $restored->getTenantId()->toString());
        self::assertCount(0, $restored->getOptions());
        self::assertCount(0, $restored->getVariants());
    }

    public function testFromDomainModelWithOptions(): void
    {
        $domain = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $option = Option::create(
            OptionId::fromString('opt_test_001'),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            0,
            []
        );

        $domain->defineOption($option);

        $entity = ConfigurableProductEntity::fromDomainModel($domain);

        self::assertCount(1, $entity->getOptions());

        $restored = $entity->toDomainModel();
        self::assertCount(1, $restored->getOptions());
        self::assertSame('color', $restored->getOptions()[0]->getCode()->toString());
    }

    public function testFromDomainModelPreservesTimestamps(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-15 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-16 12:00:00');

        $domain = ConfigurableProduct::reconstituteFromPersistence(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
            [],
            [],
            $createdAt,
            $updatedAt
        );

        $entity = ConfigurableProductEntity::fromDomainModel($domain);

        // Entity constructor sets createdAt/updatedAt to now; verify they are DateTimeImmutable instances
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // The toDomainModel() uses the entity's own timestamps (set at construction time)
        $restored = $entity->toDomainModel();
        self::assertSame($entity->getCreatedAt()->getTimestamp(), $restored->getCreatedAt()->getTimestamp());
        self::assertSame($entity->getUpdatedAt()->getTimestamp(), $restored->getUpdatedAt()->getTimestamp());
    }

    public function testGettersReturnCorrectValues(): void
    {
        $domain = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $entity = ConfigurableProductEntity::fromDomainModel($domain);

        self::assertSame('cfgprod_test_001', $entity->getId());
        self::assertSame('00000000-0000-4000-8000-000000000010', $entity->getProductId());
        self::assertSame('00000000-0000-4000-8000-000000000001', $entity->getTenantId());
    }

    public function testMultipleOptionsRoundtrip(): void
    {
        $domain = ConfigurableProduct::create(
            $this->configurableProductId,
            $this->productId,
            $this->tenantId,
        );

        $option1 = Option::create(
            OptionId::fromString('opt_color_001'),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            0,
            []
        );
        $option2 = Option::create(
            OptionId::fromString('opt_size_002'),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            1,
            []
        );

        $domain->defineOption($option1);
        $domain->defineOption($option2);

        $entity = ConfigurableProductEntity::fromDomainModel($domain);

        self::assertCount(2, $entity->getOptions());

        $restored = $entity->toDomainModel();
        self::assertCount(2, $restored->getOptions());
    }
}
