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
final class VariantCombinatorTest extends TestCase
{
    private VariantCombinator $combinator;

    public static function setUpBeforeClass(): void
    {
        class_exists(OptionValue::class);
    }

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

    #[Test]
    public function itReturnsEmptyForNoOptions(): void
    {
        $result = $this->combinator->generateCombinations([]);
        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsEmptyWhenOptionHasNoValues(): void
    {
        $option = Option::create(
            OptionId::generate(),
            OptionCode::fromString('color'),
            LocalizedString::fromArray(['en' => 'Color']),
            0,
        );

        $result = $this->combinator->generateCombinations([$option]);
        self::assertSame([], $result);
    }

    #[Test]
    public function itGeneratesCombinationsForSingleOption(): void
    {
        $option = $this->makeOption('color', 0, ['red', 'blue']);

        $result = $this->combinator->generateCombinations([$option]);

        self::assertCount(2, $result);
        self::assertSame(['color' => 'red'], $result[0]);
        self::assertSame(['color' => 'blue'], $result[1]);
    }

    #[Test]
    public function itGeneratesCartesianProduct(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue']);
        $size = $this->makeOption('size', 1, ['sm', 'lg']);

        $result = $this->combinator->generateCombinations([$color, $size]);

        self::assertCount(4, $result);
        self::assertSame(['color' => 'red', 'size' => 'sm'], $result[0]);
        self::assertSame(['color' => 'red', 'size' => 'lg'], $result[1]);
        self::assertSame(['color' => 'blue', 'size' => 'sm'], $result[2]);
        self::assertSame(['color' => 'blue', 'size' => 'lg'], $result[3]);
    }

    #[Test]
    public function itSortsByPositionBeforeGenerating(): void
    {
        $size = $this->makeOption('size', 1, ['sm']);
        $color = $this->makeOption('color', 0, ['red']);

        // Pass size first (position 1) then color (position 0)
        $result = $this->combinator->generateCombinations([$size, $color]);

        // Color should come first in keys because it has lower position
        self::assertCount(1, $result);
        $keys = array_keys($result[0]);
        self::assertSame('color', $keys[0]);
        self::assertSame('size', $keys[1]);
    }

    #[Test]
    public function itCountsPossibleCombinations(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue', 'green']);
        $size = $this->makeOption('size', 1, ['sm', 'md', 'lg']);

        self::assertSame(9, $this->combinator->countPossibleCombinations([$color, $size]));
    }

    #[Test]
    public function itCountsZeroForEmptyOptions(): void
    {
        self::assertSame(0, $this->combinator->countPossibleCombinations([]));
    }

    #[Test]
    public function itCountsZeroWhenAnyOptionHasNoValues(): void
    {
        $color = $this->makeOption('color', 0, ['red']);
        $emptyOption = Option::create(
            OptionId::generate(),
            OptionCode::fromString('size'),
            LocalizedString::fromArray(['en' => 'Size']),
            1,
        );

        self::assertSame(0, $this->combinator->countPossibleCombinations([$color, $emptyOption]));
    }

    #[Test]
    public function itValidatesCombination(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue']);
        $size = $this->makeOption('size', 1, ['sm', 'lg']);
        $options = [$color, $size];

        self::assertTrue($this->combinator->isValidCombination($options, ['color' => 'red', 'size' => 'sm']));
        self::assertTrue($this->combinator->isValidCombination($options, ['color' => 'blue', 'size' => 'lg']));
    }

    #[Test]
    public function itRejectsInvalidValueInCombination(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue']);
        $options = [$color];

        self::assertFalse($this->combinator->isValidCombination($options, ['color' => 'green']));
    }

    #[Test]
    public function itRejectsMissingOptionInCombination(): void
    {
        $color = $this->makeOption('color', 0, ['red']);
        $size = $this->makeOption('size', 1, ['sm']);
        $options = [$color, $size];

        self::assertFalse($this->combinator->isValidCombination($options, ['color' => 'red']));
    }

    #[Test]
    public function itRejectsExtraOptionInCombination(): void
    {
        $color = $this->makeOption('color', 0, ['red']);
        $options = [$color];

        self::assertFalse($this->combinator->isValidCombination($options, ['color' => 'red', 'size' => 'sm']));
    }

    #[Test]
    public function itGetsValueCodesForOption(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue', 'green']);
        $size = $this->makeOption('size', 1, ['sm']);

        $codes = $this->combinator->getValueCodesForOption(
            [$color, $size],
            OptionCode::fromString('color'),
        );

        self::assertSame(['red', 'blue', 'green'], $codes);
    }

    #[Test]
    public function itReturnsEmptyForNonExistentOption(): void
    {
        $color = $this->makeOption('color', 0, ['red']);

        $codes = $this->combinator->getValueCodesForOption(
            [$color],
            OptionCode::fromString('material'),
        );

        self::assertSame([], $codes);
    }

    #[Test]
    public function itHandlesThreeOptions(): void
    {
        $color = $this->makeOption('color', 0, ['red', 'blue']);
        $size = $this->makeOption('size', 1, ['sm', 'lg']);
        $material = $this->makeOption('material', 2, ['cotton']);

        $result = $this->combinator->generateCombinations([$color, $size, $material]);

        self::assertCount(4, $result);
        // All combinations should have material=cotton
        foreach ($result as $combo) {
            self::assertSame('cotton', $combo['material']);
        }
    }
}
