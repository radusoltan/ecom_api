<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;

/**
 * Domain service for generating variant combinations
 * Generates cartesian product of all option values
 *
 * Business Rules:
 * - Generates all possible combinations of option values
 * - Returns combinations ordered by option position
 * - Empty options result in empty combinations
 * - Maximum 1000 combinations (enforced at aggregate level)
 */
final class VariantCombinator
{
    /**
     * Generate all possible variant combinations from options
     *
     * @param Option[] $options
     * @return array<int, array<string, string>> Array of combinations, where each combination is option_code => value_code
     */
    public function generateCombinations(array $options): array
    {
        if (empty($options)) {
            return [];
        }

        // Sort options by position for consistent ordering
        $sortedOptions = $this->sortOptionsByPosition($options);

        // Build arrays of values for each option
        $optionValueArrays = [];
        $optionCodes = [];

        foreach ($sortedOptions as $option) {
            $values = $option->getValues();
            if (empty($values)) {
                // If any option has no values, we can't generate combinations
                return [];
            }

            $optionCodes[] = $option->getCode()->value();
            $optionValueArrays[] = array_map(
                fn(OptionValue $val) => $val->getCode()->value(),
                $values
            );
        }

        // Generate cartesian product
        $combinations = $this->cartesianProduct($optionValueArrays);

        // Transform to associative arrays with option codes as keys
        return array_map(
            function (array $combination) use ($optionCodes): array {
                $result = [];
                foreach ($combination as $index => $valueCode) {
                    $result[$optionCodes[$index]] = $valueCode;
                }
                return $result;
            },
            $combinations
        );
    }

    /**
     * Calculate the number of possible combinations
     *
     * @param Option[] $options
     */
    public function countPossibleCombinations(array $options): int
    {
        if (empty($options)) {
            return 0;
        }

        $count = 1;
        foreach ($options as $option) {
            $valueCount = count($option->getValues());
            if ($valueCount === 0) {
                return 0;
            }
            $count *= $valueCount;
        }

        return $count;
    }

    /**
     * Check if a specific combination is valid for given options
     *
     * @param Option[] $options
     * @param array<string, string> $combination
     */
    public function isValidCombination(array $options, array $combination): bool
    {
        // Check that combination has exactly one value per option
        if (count($combination) !== count($options)) {
            return false;
        }

        // Check that each option code in combination exists and has the specified value
        foreach ($options as $option) {
            $optionCode = $option->getCode()->value();

            if (!isset($combination[$optionCode])) {
                return false;
            }

            $valueCode = $combination[$optionCode];
            $hasValue = false;

            foreach ($option->getValues() as $optionValue) {
                if ($optionValue->getCode()->value() === $valueCode) {
                    $hasValue = true;
                    break;
                }
            }

            if (!$hasValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all value codes for a specific option code
     *
     * @param Option[] $options
     * @return string[]
     */
    public function getValueCodesForOption(array $options, OptionCode $optionCode): array
    {
        foreach ($options as $option) {
            if ($option->getCode()->equals($optionCode)) {
                return array_map(
                    fn(OptionValue $val) => $val->getCode()->value(),
                    $option->getValues()
                );
            }
        }

        return [];
    }

    /**
     * Sort options by position
     *
     * @param Option[] $options
     * @return Option[]
     */
    private function sortOptionsByPosition(array $options): array
    {
        $sorted = $options;
        usort($sorted, fn(Option $a, Option $b) => $a->getPosition() <=> $b->getPosition());
        return $sorted;
    }

    /**
     * Generate cartesian product of arrays
     *
     * @param array<int, array<int, string>> $arrays
     * @return array<int, array<int, string>>
     */
    private function cartesianProduct(array $arrays): array
    {
        if (empty($arrays)) {
            return [[]];
        }

        $result = [[]];

        foreach ($arrays as $index => $array) {
            $tmp = [];
            foreach ($result as $resultItem) {
                foreach ($array as $item) {
                    $tmp[] = array_merge($resultItem, [$item]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }
}
