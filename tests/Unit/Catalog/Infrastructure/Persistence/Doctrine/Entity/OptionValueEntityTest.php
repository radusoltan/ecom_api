<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\OptionEntity;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\OptionValueEntity;
use PHPUnit\Framework\TestCase;

final class OptionValueEntityTest extends TestCase
{
    /**
     * OptionValueId lives in the same file as OptionValue (OptionValue.php).
     * PHP's PSR-4 autoloader maps by class name, so we reference the FQCN
     * after OptionValue.php is loaded (which happens when OptionValue is first used).
     */
    private function makeOptionValue(string $rawId, string $code, array $translations, int $position): OptionValue
    {
        // Loading OptionValue class also loads OptionValueId from the same file.
        $idClass = \App\Catalog\Domain\Model\OptionValueId::class;

        return OptionValue::create(
            $idClass::fromString($rawId),
            OptionValueCode::fromString($code),
            LocalizedString::fromArray($translations),
            $position
        );
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $domainModel = $this->makeOptionValue('optval_test_001', 'red', ['en' => 'Red', 'fr' => 'Rouge'], 3);

        $entity = OptionValueEntity::fromDomainModel($domainModel);

        self::assertSame('optval_test_001', $entity->getId());
        self::assertSame('red', $entity->getCode());
        self::assertSame(['en' => 'Red', 'fr' => 'Rouge'], $entity->getNameTranslations());
        self::assertSame(3, $entity->getPosition());
        self::assertNull($entity->getOption());
    }

    public function testToDomainModelRoundtrip(): void
    {
        $domainModel = $this->makeOptionValue('optval_test_002', 'xl', ['en' => 'Extra Large'], 5);

        $entity = OptionValueEntity::fromDomainModel($domainModel);
        $restored = $entity->toDomainModel();

        self::assertSame('optval_test_002', $restored->getId()->toString());
        self::assertSame('xl', $restored->getCode()->toString());
        self::assertSame(['en' => 'Extra Large'], $restored->getNameTranslations()->toArray());
        self::assertSame(5, $restored->getPosition());
    }

    public function testFromDomainModelWithZeroPosition(): void
    {
        $domainModel = $this->makeOptionValue('optval_test_003', 'blue', ['en' => 'Blue'], 0);

        $entity = OptionValueEntity::fromDomainModel($domainModel);

        self::assertSame(0, $entity->getPosition());
    }

    public function testFromDomainModelPreservesMultipleTranslations(): void
    {
        $translations = [
            'en' => 'Small',
            'de' => 'Klein',
            'es' => 'Pequeño',
        ];
        $domainModel = $this->makeOptionValue('optval_test_004', 'small', $translations, 1);

        $entity = OptionValueEntity::fromDomainModel($domainModel);

        self::assertSame($translations, $entity->getNameTranslations());
    }

    public function testSetOptionAssociation(): void
    {
        $domainModel = $this->makeOptionValue('optval_test_005', 'green', ['en' => 'Green'], 2);
        $entity = OptionValueEntity::fromDomainModel($domainModel);

        $optionEntity = $this->createStub(OptionEntity::class);
        $entity->setOption($optionEntity);

        self::assertSame($optionEntity, $entity->getOption());
    }

    public function testSetOptionToNull(): void
    {
        $domainModel = $this->makeOptionValue('optval_test_006', 'navy-blue', ['en' => 'Navy Blue'], 0);
        $entity = OptionValueEntity::fromDomainModel($domainModel);

        $optionEntity = $this->createStub(OptionEntity::class);
        $entity->setOption($optionEntity);
        $entity->setOption(null);

        self::assertNull($entity->getOption());
    }

    public function testToDomainModelWithEmptyTranslations(): void
    {
        $domainModel = $this->makeOptionValue('optval_test_007', 'default', [], 0);

        $entity = OptionValueEntity::fromDomainModel($domainModel);
        $restored = $entity->toDomainModel();

        self::assertSame([], $restored->getNameTranslations()->toArray());
        self::assertSame('optval_test_007', $restored->getId()->toString());
    }

    public function testGettersReturnCorrectTypes(): void
    {
        $domainModel = $this->makeOptionValue('optval_test_008', 'cotton', ['en' => 'Cotton'], 10);

        $entity = OptionValueEntity::fromDomainModel($domainModel);

        self::assertIsString($entity->getId());
        self::assertIsString($entity->getCode());
        self::assertIsArray($entity->getNameTranslations());
        self::assertIsInt($entity->getPosition());
    }
}
