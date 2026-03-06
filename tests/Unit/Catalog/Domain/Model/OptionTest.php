<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\Model\OptionValueId;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Option::class)]
#[CoversClass(OptionId::class)]
#[CoversClass(OptionValue::class)]
#[CoversClass(OptionValueId::class)]
final class OptionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // OptionValueId is defined in OptionValue.php — force loading
        class_exists(OptionValue::class);
    }

    private function makeOption(string $code = 'color', int $position = 0, array $values = []): Option
    {
        return Option::create(
            id: OptionId::generate(),
            code: OptionCode::fromString($code),
            nameTranslations: LocalizedString::fromArray(['en' => ucfirst($code)]),
            position: $position,
            values: $values,
        );
    }

    private function makeOptionValue(string $code = 'red', int $position = 0): OptionValue
    {
        return OptionValue::create(
            id: OptionValueId::generate(),
            code: OptionValueCode::fromString($code),
            nameTranslations: LocalizedString::fromArray(['en' => ucfirst($code)]),
            position: $position,
        );
    }

    // -------------------------------------------------------
    // OptionId
    // -------------------------------------------------------

    #[Test]
    public function optionIdGeneratesUniqueValues(): void
    {
        $id1 = OptionId::generate();
        $id2 = OptionId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function optionIdCreatesFromString(): void
    {
        $id = OptionId::fromString('opt_12345');

        self::assertSame('opt_12345', $id->toString());
    }

    #[Test]
    public function optionIdThrowsOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OptionId::fromString('');
    }

    #[Test]
    public function optionIdEquality(): void
    {
        $id1 = OptionId::fromString('opt_abc');
        $id2 = OptionId::fromString('opt_abc');
        $id3 = OptionId::fromString('opt_xyz');

        self::assertTrue($id1->equals($id2));
        self::assertFalse($id1->equals($id3));
    }

    // -------------------------------------------------------
    // Option creation
    // -------------------------------------------------------

    #[Test]
    public function itCreatesOptionWithRequiredFields(): void
    {
        $id = OptionId::generate();
        $code = OptionCode::fromString('color');
        $translations = LocalizedString::fromArray(['en' => 'Color']);

        $option = Option::create($id, $code, $translations, 1, []);

        self::assertTrue($option->getId()->equals($id));
        self::assertTrue($option->getCode()->equals($code));
        self::assertSame(1, $option->getPosition());
        self::assertCount(0, $option->getValues());
    }

    #[Test]
    public function itCreatesOptionWithInitialValues(): void
    {
        $value = $this->makeOptionValue('red');

        $option = $this->makeOption('color', 0, [$value]);

        self::assertCount(1, $option->getValues());
    }

    #[Test]
    public function itThrowsWhenInitialValuesContainDuplicateCodes(): void
    {
        $value1 = $this->makeOptionValue('red');
        $value2 = $this->makeOptionValue('red'); // duplicate code

        $this->expectException(\DomainException::class);

        $this->makeOption('color', 0, [$value1, $value2]);
    }

    // -------------------------------------------------------
    // addValue
    // -------------------------------------------------------

    #[Test]
    public function itAddsValue(): void
    {
        $option = $this->makeOption('color');
        $value = $this->makeOptionValue('red');

        $option->addValue($value);

        self::assertCount(1, $option->getValues());
    }

    #[Test]
    public function itThrowsOnDuplicateValueCode(): void
    {
        $option = $this->makeOption('color');
        $value1 = $this->makeOptionValue('red');
        $value2 = $this->makeOptionValue('red');

        $option->addValue($value1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Value with code "red" already exists');

        $option->addValue($value2);
    }

    // -------------------------------------------------------
    // removeValue
    // -------------------------------------------------------

    #[Test]
    public function itRemovesValueByCode(): void
    {
        $option = $this->makeOption('color');
        $red = $this->makeOptionValue('red');
        $blue = $this->makeOptionValue('blue');
        $option->addValue($red);
        $option->addValue($blue);

        $option->removeValue(OptionValueCode::fromString('red'));

        $values = array_values($option->getValues());
        self::assertCount(1, $values);
        self::assertSame('blue', $values[0]->getCode()->value());
    }

    // -------------------------------------------------------
    // getValueByCode / hasValue
    // -------------------------------------------------------

    #[Test]
    public function itFindsValueByCode(): void
    {
        $option = $this->makeOption('color');
        $red = $this->makeOptionValue('red');
        $option->addValue($red);

        $found = $option->getValueByCode(OptionValueCode::fromString('red'));

        self::assertNotNull($found);
        self::assertSame('red', $found->getCode()->value());
    }

    #[Test]
    public function itReturnsNullForNonExistentValueCode(): void
    {
        $option = $this->makeOption('color');

        $result = $option->getValueByCode(OptionValueCode::fromString('green'));

        self::assertNull($result);
    }

    #[Test]
    public function itChecksValueExists(): void
    {
        $option = $this->makeOption('color');
        $red = $this->makeOptionValue('red');
        $option->addValue($red);

        self::assertTrue($option->hasValue(OptionValueCode::fromString('red')));
        self::assertFalse($option->hasValue(OptionValueCode::fromString('blue')));
    }

    // -------------------------------------------------------
    // updatePosition
    // -------------------------------------------------------

    #[Test]
    public function itUpdatesPosition(): void
    {
        $option = $this->makeOption('color', 0);

        $option->updatePosition(5);

        self::assertSame(5, $option->getPosition());
    }

    #[Test]
    public function itThrowsOnNegativePosition(): void
    {
        $option = $this->makeOption('color');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be non-negative');

        $option->updatePosition(-1);
    }

    // -------------------------------------------------------
    // updateNameTranslations
    // -------------------------------------------------------

    #[Test]
    public function itUpdatesNameTranslations(): void
    {
        $option = $this->makeOption('color');
        $newTranslations = LocalizedString::fromArray(['en' => 'Colour', 'fr' => 'Couleur']);

        $option->updateNameTranslations($newTranslations);

        self::assertTrue($option->getNameTranslations()->equals($newTranslations));
    }

    // -------------------------------------------------------
    // getSortedValues
    // -------------------------------------------------------

    #[Test]
    public function itReturnsSortedValuesByPosition(): void
    {
        $option = $this->makeOption('color');
        $option->addValue($this->makeOptionValue('blue', 2));
        $option->addValue($this->makeOptionValue('red', 0));
        $option->addValue($this->makeOptionValue('green', 1));

        $sorted = $option->getSortedValues();

        self::assertSame('red', $sorted[0]->getCode()->value());
        self::assertSame('green', $sorted[1]->getCode()->value());
        self::assertSame('blue', $sorted[2]->getCode()->value());
    }

    // -------------------------------------------------------
    // equals
    // -------------------------------------------------------

    #[Test]
    public function itEqualsSameOptionById(): void
    {
        $id = OptionId::generate();
        $option1 = Option::create($id, OptionCode::fromString('color'), LocalizedString::fromArray(['en' => 'Color']));
        $option2 = Option::create($id, OptionCode::fromString('color'), LocalizedString::fromArray(['en' => 'Color']));

        self::assertTrue($option1->equals($option2));
    }

    #[Test]
    public function itDoesNotEqualDifferentOption(): void
    {
        $option1 = $this->makeOption('color');
        $option2 = $this->makeOption('size');

        self::assertFalse($option1->equals($option2));
    }

    // -------------------------------------------------------
    // OptionValue
    // -------------------------------------------------------

    #[Test]
    public function optionValueCreatesCorrectly(): void
    {
        $id = OptionValueId::generate();
        $code = OptionValueCode::fromString('red');
        $translations = LocalizedString::fromArray(['en' => 'Red']);

        $value = OptionValue::create($id, $code, $translations, 3);

        self::assertTrue($value->getId()->equals($id));
        self::assertTrue($value->getCode()->equals($code));
        self::assertSame(3, $value->getPosition());
    }

    #[Test]
    public function optionValueReconstitutesFromPersistence(): void
    {
        $id = OptionValueId::fromString('optval_test');
        $code = OptionValueCode::fromString('blue');
        $translations = LocalizedString::fromArray(['en' => 'Blue']);

        $value = OptionValue::reconstituteFromPersistence($id, $code, $translations, 2);

        self::assertSame('blue', $value->getCode()->value());
        self::assertSame(2, $value->getPosition());
    }

    #[Test]
    public function optionValueThrowsOnNegativePosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be non-negative');

        OptionValue::create(
            OptionValueId::generate(),
            OptionValueCode::fromString('red'),
            LocalizedString::fromArray(['en' => 'Red']),
            -1,
        );
    }

    #[Test]
    public function optionValueUpdatesPosition(): void
    {
        $value = $this->makeOptionValue('red', 0);

        $value->updatePosition(10);

        self::assertSame(10, $value->getPosition());
    }

    #[Test]
    public function optionValueUpdatesNameTranslations(): void
    {
        $value = $this->makeOptionValue('red');
        $newTranslations = LocalizedString::fromArray(['en' => 'Crimson', 'fr' => 'Rouge']);

        $value->updateNameTranslations($newTranslations);

        self::assertTrue($value->getNameTranslations()->equals($newTranslations));
    }

    #[Test]
    public function optionValueEquality(): void
    {
        $id = OptionValueId::generate();
        $translations = LocalizedString::fromArray(['en' => 'Red']);
        $value1 = OptionValue::create($id, OptionValueCode::fromString('red'), $translations);
        $value2 = OptionValue::create($id, OptionValueCode::fromString('red'), $translations);
        $value3 = $this->makeOptionValue('blue');

        self::assertTrue($value1->equals($value2));
        self::assertFalse($value1->equals($value3));
    }

    // -------------------------------------------------------
    // OptionValueId
    // -------------------------------------------------------

    #[Test]
    public function optionValueIdGeneratesUnique(): void
    {
        $id1 = OptionValueId::generate();
        $id2 = OptionValueId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function optionValueIdCreatesFromString(): void
    {
        $id = OptionValueId::fromString('optval_abc');

        self::assertSame('optval_abc', $id->toString());
        self::assertSame('optval_abc', $id->value());
        self::assertSame('optval_abc', (string) $id);
    }

    #[Test]
    public function optionValueIdThrowsOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OptionValueId::fromString('');
    }
}
