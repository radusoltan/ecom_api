<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Service;

use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\Model\OptionValueId;
use App\Catalog\Domain\Service\VariantCombinator;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantCombinator::class)]
final class VariantCombinatorAdditionalTest extends TestCase
{
    private VariantCombinator $combinator;

    protected function setUp(): void
    {
        $this->combinator = new VariantCombinator();
    }

    private function makeOption(string $code, int $position, array $valueCodes): Option
    {
        $values = [];
        foreach ($valueCodes as $i => $vc) {
            $values[] = OptionValue::create(
                OptionValueId::generate(),
                OptionValueCode::fromString($vc),
                LocalizedString::fromArray(['en' => ucfirst($vc)]),
                $i,
            );
        }

        return Option::create(
            OptionId::generate(),
            OptionCode::fromString($code),
            LocalizedString::fromArray(['en' => ucfirst($code)]),
            $position,
            $values,
        );
    }

    // -----------------------------------------------------------------------
    // generateCombinations() – more thorough coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesCombinationsForSingleOptionWithSingleValue(): void
    {
        $option = $this->makeOption('size', 0, ['xl']);
        $result = $this->combinator->generateCombinations([$option]);

        self::assertCount(1, $result);
        self::assertSame(['size' => 'xl'], $result[0]);
    }

    #[Test]
    public function itGeneratesCombinationsWithManyValues(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue', 'green', 'yellow', 'black']);
        $result = $this->combinator->generateCombinations([$color]);

        self::assertCount(5, $result);
        $valueCodes = array_column($result, 'color');
        self::assertContains('red', $valueCodes);
        self::assertContains('yellow', $valueCodes);
        self::assertContains('black', $valueCodes);
    }

    #[Test]
    public function itReturnsEmptyWhenOneOfMultipleOptionsHasNoValues(): void
    {
        $color = $this->makeOption('color', 0, ['red']);
        $emptySize = Option::create(
            OptionId::generate(),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            1,
            [], // no values
        );

        $result = $this->combinator->generateCombinations([$color, $emptySize]);

        self::assertSame([], $result);
    }

    #[Test]
    public function itSortsOptionsByPositionBeforeCartesian(): void
    {
        // Pass in reverse position order
        $material = $this->makeOption('material', 2, ['cotton', 'polyester']);
        $size = $this->makeOption('size', 1, ['sm', 'lg']);
        $color = $this->makeOption('color', 0, ['red', 'blue']);

        $result = $this->combinator->generateCombinations([$material, $size, $color]);

        // Should generate 2×2×2 = 8 combinations
        self::assertCount(8, $result);

        // First key in every combination should be 'color' (lowest position)
        foreach ($result as $combo) {
            $keys = array_keys($combo);
            self::assertSame('color', $keys[0]);
            self::assertSame('size', $keys[1]);
            self::assertSame('material', $keys[2]);
        }
    }

    #[Test]
    public function itGeneratesCombinationsForThreeOptionsWithThreeValues(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue', 'green']);
        $size = $this->makeOption('size', 1, ['sm', 'md', 'lg']);
        $fit = $this->makeOption('fit', 2, ['slim', 'regular', 'loose']);

        $result = $this->combinator->generateCombinations([$color, $size, $fit]);

        // 3×3×3 = 27 combinations
        self::assertCount(27, $result);
    }

    #[Test]
    public function itAllCombinationKeysMatchOptionCodes(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue']);
        $size = $this->makeOption('size', 1, ['sm', 'lg']);

        $result = $this->combinator->generateCombinations([$color, $size]);

        foreach ($result as $combo) {
            self::assertArrayHasKey('color', $combo);
            self::assertArrayHasKey('size', $combo);
            self::assertCount(2, $combo);
        }
    }

    // -----------------------------------------------------------------------
    // countPossibleCombinations() – more edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itCountsOneForSingleOptionWithOneValue(): void
    {
        $opt = $this->makeOption('size', 0, ['xl']);
        self::assertSame(1, $this->combinator->countPossibleCombinations([$opt]));
    }

    #[Test]
    public function itCountsCorrectlyForThreeOptions(): void
    {
        $c = $this->makeOption('color', 0, ['r', 'g', 'b']);
        $s = $this->makeOption('size', 1, ['sm', 'lg']);
        $f = $this->makeOption('fit', 2, ['slim', 'regular']);

        self::assertSame(12, $this->combinator->countPossibleCombinations([$c, $s, $f]));
    }

    // -----------------------------------------------------------------------
    // isValidCombination() – edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itValidatesEmptyCombinationForNoOptions(): void
    {
        // No options, empty combination → valid (vacuously)
        self::assertTrue($this->combinator->isValidCombination([], []));
    }

    #[Test]
    public function itRejectsEmptyCombinationWhenOptionsExist(): void
    {
        $color = $this->makeOption('color', 0, ['red']);
        self::assertFalse($this->combinator->isValidCombination([$color], []));
    }

    #[Test]
    public function itValidatesCombinationWithSingleOption(): void
    {
        $size = $this->makeOption('size', 0, ['sm', 'md', 'lg']);

        self::assertTrue($this->combinator->isValidCombination([$size], ['size' => 'md']));
        self::assertFalse($this->combinator->isValidCombination([$size], ['size' => 'xl']));
    }

    #[Test]
    public function itRejectsWhenCombinationHasUnknownOptionKey(): void
    {
        $color = $this->makeOption('color', 0, ['red']);

        // 'material' is not a defined option
        self::assertFalse($this->combinator->isValidCombination([$color], ['material' => 'cotton']));
    }

    #[Test]
    public function itRejectsWhenCombinationMissingOneOption(): void
    {
        $color = $this->makeOption('color', 0, ['red']);
        $size = $this->makeOption('size', 1, ['sm']);

        // Only provides color, missing size
        self::assertFalse($this->combinator->isValidCombination([$color, $size], ['color' => 'red']));
    }

    #[Test]
    public function itRejectsWhenCombinationHasExtraKey(): void
    {
        $color = $this->makeOption('color', 0, ['red']);

        // Extra 'size' key not in options
        self::assertFalse($this->combinator->isValidCombination([$color], ['color' => 'red', 'size' => 'lg']));
    }

    // -----------------------------------------------------------------------
    // getValueCodesForOption() – edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsAllValueCodesForExistingOption(): void
    {
        $size = $this->makeOption('size', 0, ['xs', 'sm', 'md', 'lg', 'xl']);

        $codes = $this->combinator->getValueCodesForOption([$size], OptionCode::fromString('size'));

        self::assertSame(['xs', 'sm', 'md', 'lg', 'xl'], $codes);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenOptionCodeNotFound(): void
    {
        $color = $this->makeOption('color', 0, ['red']);

        $codes = $this->combinator->getValueCodesForOption([$color], OptionCode::fromString('size'));

        self::assertSame([], $codes);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenOptionsListEmpty(): void
    {
        $codes = $this->combinator->getValueCodesForOption([], OptionCode::fromString('color'));
        self::assertSame([], $codes);
    }

    #[Test]
    public function itReturnsCorrectCodesWhenMultipleOptionsExist(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue']);
        $size = $this->makeOption('size', 1, ['sm', 'lg', 'xl']);

        $colorCodes = $this->combinator->getValueCodesForOption([$color, $size], OptionCode::fromString('color'));
        $sizeCodes = $this->combinator->getValueCodesForOption([$color, $size], OptionCode::fromString('size'));

        self::assertSame(['red', 'blue'], $colorCodes);
        self::assertSame(['sm', 'lg', 'xl'], $sizeCodes);
    }

    #[Test]
    public function itReturnsEmptyCodesForOptionWithNoValues(): void
    {
        $emptyOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('empty'),
            LocalizedString::fromArray(['en' => 'Empty']),
            0,
            [],
        );

        $codes = $this->combinator->getValueCodesForOption([$emptyOption], OptionCode::fromString('empty'));

        self::assertSame([], $codes);
    }

    // -----------------------------------------------------------------------
    // countPossibleCombinations + generateCombinations consistency
    // -----------------------------------------------------------------------

    #[Test]
    public function itCountMatchesGeneratedCombinationsCount(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue', 'green']);
        $size = $this->makeOption('size', 1, ['sm', 'md', 'lg', 'xl']);

        $count = $this->combinator->countPossibleCombinations([$color, $size]);
        $generated = $this->combinator->generateCombinations([$color, $size]);

        self::assertSame($count, count($generated));
        self::assertSame(12, $count);
    }

    #[Test]
    public function itCountMatchesWhenSingleOption(): void
    {
        $opt = $this->makeOption('material', 0, ['cotton', 'polyester', 'wool']);

        $count = $this->combinator->countPossibleCombinations([$opt]);
        $generated = $this->combinator->generateCombinations([$opt]);

        self::assertSame(3, $count);
        self::assertSame(3, count($generated));
    }

    #[Test]
    public function itCountZeroMatchesEmptyGenerated(): void
    {
        $emptyOpt = Option::create(
            OptionId::generate(),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            0,
            [],
        );

        $count = $this->combinator->countPossibleCombinations([$emptyOpt]);
        $generated = $this->combinator->generateCombinations([$emptyOpt]);

        self::assertSame(0, $count);
        self::assertSame(0, count($generated));
    }
}
