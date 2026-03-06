<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ConfigurableProductEntity;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\OptionEntity;
use PHPUnit\Framework\TestCase;

final class OptionEntityTest extends TestCase
{
    private OptionId $optionId;

    protected function setUp(): void
    {
        // OptionId and OptionValue/OptionValueId are defined in the same files as Option/OptionValue;
        // trigger class loading by referencing them first.
        class_exists(Option::class);
        class_exists(OptionValue::class);
        $this->optionId = OptionId::fromString('opt_test_00001');
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $domain = Option::create(
            $this->optionId,
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color', 'fr' => 'Couleur']),
            1,
            []
        );

        $entity = OptionEntity::fromDomainModel($domain);

        self::assertSame($this->optionId->toString(), $entity->getId());
        self::assertSame('color', $entity->getCode());
        self::assertSame(1, $entity->getPosition());
        self::assertSame(['en' => 'Color', 'fr' => 'Couleur'], $entity->getNameTranslations());
        self::assertCount(0, $entity->getValues());
        self::assertNull($entity->getConfigurableProduct());

        $restored = $entity->toDomainModel();
        self::assertSame($this->optionId->toString(), $restored->getId()->toString());
        self::assertSame('color', $restored->getCode()->toString());
        self::assertSame(1, $restored->getPosition());
        self::assertSame(['en' => 'Color', 'fr' => 'Couleur'], $restored->getNameTranslations()->toArray());
        self::assertCount(0, $restored->getValues());
    }

    public function testFromDomainModelWithPositionZero(): void
    {
        $domain = Option::create(
            $this->optionId,
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            0,
            []
        );

        $entity = OptionEntity::fromDomainModel($domain);

        self::assertSame(0, $entity->getPosition());
        self::assertSame('size', $entity->getCode());

        $restored = $entity->toDomainModel();
        self::assertSame(0, $restored->getPosition());
    }

    public function testFromDomainModelWithValues(): void
    {
        $redValue = OptionValue::create(
            \App\Catalog\Domain\Model\OptionValueId::fromString('oval_red_001'),
            \App\Catalog\Domain\ValueObject\OptionValueCode::fromString('red'),
            LocalizedString::fromArray(['en' => 'Red']),
            0
        );
        $blueValue = OptionValue::create(
            \App\Catalog\Domain\Model\OptionValueId::fromString('oval_blue_002'),
            \App\Catalog\Domain\ValueObject\OptionValueCode::fromString('blue'),
            LocalizedString::fromArray(['en' => 'Blue']),
            1
        );

        $domain = Option::create(
            $this->optionId,
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            0,
            [$redValue, $blueValue]
        );

        $entity = OptionEntity::fromDomainModel($domain);

        self::assertCount(2, $entity->getValues());

        $restored = $entity->toDomainModel();
        self::assertCount(2, $restored->getValues());
    }

    public function testSetConfigurableProduct(): void
    {
        $domain = Option::create(
            $this->optionId,
            OptionCode::fromString('material'),
            LocalizedString::fromArray(['en' => 'Material']),
            2,
            []
        );

        $entity = OptionEntity::fromDomainModel($domain);
        self::assertNull($entity->getConfigurableProduct());

        // Load ConfigurableProduct to ensure ConfigurableProductId is available
        class_exists(ConfigurableProduct::class);
        $configurableProduct = ConfigurableProduct::create(
            ConfigurableProductId::fromString('cfgprod_parent_001'),
            \App\Catalog\Domain\Model\ProductId::fromString('00000000-0000-4000-8000-000000000010'),
            \App\Shared\Domain\ValueObject\TenantId::fromString('00000000-0000-4000-8000-000000000001')
        );
        $configurableProductEntity = ConfigurableProductEntity::fromDomainModel($configurableProduct);
        $entity->setConfigurableProduct($configurableProductEntity);

        self::assertSame($configurableProductEntity, $entity->getConfigurableProduct());
    }

    public function testSetConfigurableProductNull(): void
    {
        $domain = Option::create(
            $this->optionId,
            OptionCode::fromString('style'),
            LocalizedString::fromArray(['en' => 'Style']),
            0,
            []
        );

        $entity = OptionEntity::fromDomainModel($domain);
        $entity->setConfigurableProduct(null);

        self::assertNull($entity->getConfigurableProduct());
    }

    public function testNameTranslationsWithMultipleLocales(): void
    {
        $translations = [
            'en' => 'Size',
            'fr' => 'Taille',
            'de' => 'Größe',
            'es' => 'Talla',
        ];

        $domain = Option::create(
            $this->optionId,
            OptionCode::fromString('size'),
            LocalizedString::fromArray($translations),
            3,
            []
        );

        $entity = OptionEntity::fromDomainModel($domain);

        self::assertSame($translations, $entity->getNameTranslations());

        $restored = $entity->toDomainModel();
        self::assertSame($translations, $restored->getNameTranslations()->toArray());
    }

    public function testRoundtripPreservesOptionCodeExactly(): void
    {
        $domain = Option::create(
            $this->optionId,
            OptionCode::fromString('shoe_size'),
            LocalizedString::fromArray(['en' => 'Shoe Size']),
            0,
            []
        );

        $entity = OptionEntity::fromDomainModel($domain);
        self::assertSame('shoe_size', $entity->getCode());

        $restored = $entity->toDomainModel();
        self::assertSame('shoe_size', $restored->getCode()->toString());
    }
}
